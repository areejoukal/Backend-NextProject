<?php

namespace App\Services\AI\Prompts;

use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\Exceptions\AIInvalidResponseException;

class AnalysisPrompt extends BasePrompt
{
    private array $userState = [];
    private array $answers = [];

    public function name(): string
    {
        return 'analysis';
    }

    public function recommendedModel(): ?string
    {
        return null; // استخدم الافتراضي (gpt-oss-120b)
    }

    public function temperature(): float
    {
        return 0.4; // نريد بعض التنوع في الصياغة
    }

    // ==========================================================
    // Setters
    // ==========================================================

    public function withUserState(array $state): self
    {
        $this->userState = $state;
        return $this;
    }

    public function withAnswers(array $answers): self
    {
        $this->answers = $answers;
        return $this;
    }

    // ==========================================================
    // Schema
    // ==========================================================

    public function schema(): ?array
    {
        return [
            'headline' => ['type' => 'string'],
            'summary' => ['type' => 'string'],
            'known' => ['type' => 'array', 'items' => 'string'],
            'unknown' => ['type' => 'array', 'items' => 'string'],
            'controllable' => ['type' => 'array', 'items' => 'string'],
            'uncontrollable' => ['type' => 'array', 'items' => 'string'],
        ];
    }

    // ==========================================================
    // Build
    // ==========================================================

    public function build(): AIRequest
    {
        return AIRequest::make([
            $this->system($this->buildSystemPrompt()),
            $this->user($this->buildUserPrompt()),
        ])
            ->withTemperature($this->temperature())
            ->forPurpose($this->name());
    }

    // ==========================================================
    // Validation
    // ==========================================================

    protected function validate(array $data): void
    {
        $errors = [];

        // الحد الأدنى من العناصر
        foreach (['known', 'unknown', 'controllable', 'uncontrollable'] as $key) {
            if (empty($data[$key]) || !is_array($data[$key])) {
                $errors[] = "{$key} يجب أن يكون قائمة غير فارغة";
            }
        }

        // headline لا يزيد عن 200 حرف
        if (isset($data['headline']) && mb_strlen($data['headline']) > 200) {
            $errors[] = 'headline طويل جدًا (الحد 200 حرف)';
        }

        // summary لا يزيد عن 800 حرف
        if (isset($data['summary']) && mb_strlen($data['summary']) > 800) {
            $errors[] = 'summary طويل جدًا (الحد 800 حرف)';
        }

        if (!empty($errors)) {
            throw AIInvalidResponseException::schemaValidationFailed($errors);
        }
    }

    // ==========================================================
    // Prompt Building
    // ==========================================================

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
أنت محلل وضع في تطبيق NEXT.

مهمتك: توليد "صورة حالية" واقعية ودقيقة للمستخدم بناءً على هدفه وإجاباته.

القواعد الصارمة:
1. لا تقدم وعودًا ("ستحصل على..."، "ستنجح في...")
2. لا تخترع معلومات لم يذكرها المستخدم
3. افصل بوضوح بين:
   - ما يعرفه (معلومات واضحة)
   - ما لا يعرفه (نقاط غامضة)
   - ما يمكنه التأثير فيه (ضمن سيطرته)
   - ما هو خارج سيطرته (لا يملك قراره)
4. استخدم العربية الفصحى البسيطة
5. كن صريحًا: إذا كانت الصورة ناقصة، قل ذلك
6. لا تتجاوز الحدود المحددة للنصوص

المبادئ:
- "المعروف" هو ما ذكره المستخدم صراحة أو استنتجناه بوضوح
- "غير المعروف" هو ما يحتاج توضيحًا قبل اتخاذ قرار
- "تحت السيطرة" هو ما يستطيع فعله بنفسه (تعلم، بناء، تواصل)
- "خارج السيطرة" هو ما لا يملك قراره (قبول، سوق، منافسة)
PROMPT;
    }

    private function buildUserPrompt(): string
    {
        $goal = $this->formatGoal();
        $profile = $this->formatProfile();
        $answers = $this->formatAnswers();

        return <<<PROMPT
## هدف المستخدم

{$goal}

## ملفه الشخصي

{$profile}

## إجاباته على التقييم

{$answers}

## مهمتك

حلّل وضع المستخدم وأعد JSON فقط بهذا الشكل:

{
  "headline": "جملة واحدة (حتى 120 حرف) — التشخيص الأساسي",
  "summary": "فقرة قصيرة (3-4 جمل، حتى 800 حرف) — الشرح",
  "known": ["نقطة 1", "نقطة 2", "نقطة 3"],
  "unknown": ["نقطة 1", "نقطة 2"],
  "controllable": ["نقطة 1", "نقطة 2", "نقطة 3"],
  "uncontrollable": ["نقطة 1", "نقطة 2"]
}

قواعد:
- كل قائمة: بين 2 و 5 عناصر
- كل عنصر: قصير وواضح (حتى 80 حرف)
- لا تكرر نفس المعنى بين القوائم
- headline يجب أن يكون تشخيصًا، لا وصفًا عامًا
- استخدم معلومات المستخدم الفعلية، لا عبارات عامة
PROMPT;
    }

    // ==========================================================
    // Formatters
    // ==========================================================

    private function formatGoal(): string
    {
        $goal = $this->userState['goal'] ?? [];
        if (empty($goal)) {
            return 'غير محدد';
        }

        $lines = [
            "النوع: " . ($goal['type'] ?? 'غير محدد'),
            "العنوان: " . ($goal['title'] ?? 'غير محدد'),
        ];

        if (!empty($goal['details']) && is_array($goal['details'])) {
            foreach ($goal['details'] as $key => $value) {
                if (!empty($value)) {
                    $lines[] = "{$key}: {$value}";
                }
            }
        }

        return implode("\n", $lines);
    }

    private function formatProfile(): string
    {
        $profile = $this->userState['profile'] ?? [];
        if (empty($profile)) {
            return 'غير محدد';
        }

        $map = [
            'age' => 'العمر',
            'location' => 'الموقع',
            'education' => 'التعليم',
            'field' => 'المجال',
            'experience' => 'الخبرة',
            'background' => 'خلفية',
        ];

        $lines = [];
        foreach ($map as $key => $label) {
            if (!empty($profile[$key])) {
                $lines[] = "{$label}: {$profile[$key]}";
            }
        }

        return empty($lines) ? 'غير محدد' : implode("\n", $lines);
    }

    private function formatAnswers(): string
    {
        if (empty($this->answers)) {
            return '(لا توجد إجابات)';
        }

        $lines = [];
        foreach ($this->answers as $i => $answer) {
            $num = $i + 1;
            $q = $answer['question_text'] ?? $answer['question_id'] ?? '?';
            $a = is_array($answer['answer'] ?? null)
                ? implode(', ', $answer['answer'])
                : ($answer['answer'] ?? '—');

            $lines[] = "{$num}. س: {$q}";
            $lines[] = "   ج: {$a}";
        }

        return implode("\n", $lines);
    }
}