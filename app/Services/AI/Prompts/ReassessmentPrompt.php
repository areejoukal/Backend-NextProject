<?php

namespace App\Services\AI\Prompts;

use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\Exceptions\AIInvalidResponseException;

class ReassessmentPrompt extends BasePrompt
{
    private array $userState = [];
    private array $previousAnswers = [];

    public function name(): string
    {
        return 'reassessment';
    }

    public function recommendedModel(): ?string
    {
        return null;
    }

    public function temperature(): float
    {
        return 0.5; // أعلى شوية للإبداع
    }

    public function withUserState(array $state): self
    {
        $this->userState = $state;
        return $this;
    }

    public function withPreviousAnswers(array $answers): self
    {
        $this->previousAnswers = $answers;
        return $this;
    }

    // ⛔ حذفنا withAvailableQuestions

    public function schema(): ?array
    {
        return [
            'changed'   => ['type' => 'array', 'items' => 'string'],
            'preserved' => ['type' => 'array', 'items' => 'string'],
            'questions' => ['type' => 'array', 'items' => 'object'],
            'summary'   => ['type' => 'string'],
        ];
    }

    public function build(): AIRequest
    {
        return AIRequest::make([
            $this->system($this->buildSystemPrompt()),
            $this->user($this->buildUserPrompt()),
        ])
            ->withTemperature($this->temperature())
            ->asJson()                    // ← مهم: JSON mode
            ->forPurpose($this->name());
    }

    protected function validate(array $data): void
    {
        $errors = [];

        if (empty($data['questions']) || !is_array($data['questions'])) {
            $errors[] = 'questions مطلوب';
        } else {
            $count = count($data['questions']);
            if ($count < 2 || $count > 4) {
                $errors[] = "questions يجب أن تكون 2-4، وُجد {$count}";
            }

            foreach ($data['questions'] as $i => $q) {
                if (empty($q['text'])) {
                    $errors[] = "questions[{$i}].text مطلوب";
                }
                if (empty($q['type'])) {
                    $errors[] = "questions[{$i}].type مطلوب";
                }
                if (empty($q['reason'])) {
                    $errors[] = "questions[{$i}].reason مطلوب";
                }
            }
        }

        if (empty($data['summary'])) {
            $errors[] = 'summary مطلوب';
        }

        if (!empty($errors)) {
            throw AIInvalidResponseException::schemaValidationFailed($errors);
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
أنت محرّك إعادة التقييم في تطبيق NEXT.

المستخدم أكمل تقييمًا سابقًا ويريد إعادة تقييم وضعه.
مهمتك:
1. تحديد ما تغيّر وما بقي ثابتًا
2. **توليد 2-4 أسئلة جديدة كاملة من الصفر** (نص + خيارات + نوع)

## قواعد توليد الأسئلة

- كل سؤال **جديد تماماً** — لا تكرر أي سؤال سابق
- الأسئلة يجب أن تكون:
  * قصيرة (أقل من 20 كلمة)
  * مباشرة وواضحة
  * بالعربية الفصحى المبسطة
  * محددة (ليس عامة)
  * مرتبطة بالتغييرات المحتملة

## أنواع الأسئلة

اختر النوع المناسب لكل سؤال:
- `single_choice`: سؤال بخيارات، اختر واحد
- `multi_choice`: سؤال بخيارات، اختر أكثر من واحد
- `scale`: مقياس (مثلاً 1-5)
- `short_text`: إجابة نصية قصيرة

## صيغة الرد (JSON فقط)

{
  "changed": ["جانب تغيّر 1", "جانب تغيّر 2"],
  "preserved": ["جانب ثابت 1", "جانب ثابت 2"],
  "questions": [
    {
      "id": "q_reassess_1",
      "text": "نص السؤال الجديد؟",
      "type": "single_choice",
      "options": ["خيار 1", "خيار 2", "خيار 3"],
      "reason": "لماذا نسأل هذا السؤال الآن؟",
      "expected_info_gain": 0.7
    }
  ],
  "summary": "جملة أو جملتان تصفان الوضع الجديد المتوقع"
}

## قواعد مهمة
- إذا كان النوع `short_text` → `options: null`
- إذا كان النوع `single_choice` أو `multi_choice` → `options` مصفوفة ب2-5 خيارات
- إذا كان النوع `scale` → `options` مصفوفة أرقام أو تسميات
- `id` فريد لكل سؤال
- كل الأسئلة بالعربية
PROMPT;
    }

    private function buildUserPrompt(): string
    {
        $goal = $this->formatGoal();
        $profile = $this->formatProfile();
        $previous = $this->formatPreviousAnswers();

        return <<<PROMPT
## سياق المستخدم

### الهدف الحالي
{$goal}

### الملف الشخصي
{$profile}

## إجاباته في التقييم السابق

{$previous}

## مهمتك

1. حدّد ما تغيّر على الأرجح
2. حدّد ما زال ثابتًا
3. **ولّد 2-4 أسئلة جديدة** تركز على الجوانب المتغيرة
4. اكتب ملخصاً قصيراً للوضع الجديد

أعد JSON فقط.
PROMPT;
    }

    private function formatGoal(): string
    {
        $goal = $this->userState['goal'] ?? [];
        if (empty($goal)) {
            return 'غير محدد';
        }

        return sprintf(
            "النوع: %s | العنوان: %s",
            $goal['type'] ?? '—',
            $goal['title'] ?? '—'
        );
    }

    private function formatProfile(): string
    {
        $profile = $this->userState['profile'] ?? [];
        if (empty($profile)) {
            return 'غير محدد';
        }

        $parts = [];
        foreach (['experience', 'education', 'field'] as $key) {
            if (!empty($profile[$key])) {
                $parts[] = "{$key}: {$profile[$key]}";
            }
        }

        return empty($parts) ? 'غير محدد' : implode(' | ', $parts);
    }

    private function formatPreviousAnswers(): string
    {
        if (empty($this->previousAnswers)) {
            return '(لا توجد إجابات سابقة)';
        }

        $lines = [];
        foreach ($this->previousAnswers as $i => $a) {
            $num = $i + 1;
            $q = $a['question_text'] ?? '?';
            $ans = is_array($a['answer'] ?? null)
                ? implode(', ', $a['answer'])
                : ($a['answer'] ?? '—');

            $lines[] = "{$num}. {$q}";
            $lines[] = "   → {$ans}";
        }

        return implode("\n", $lines);
    }
}