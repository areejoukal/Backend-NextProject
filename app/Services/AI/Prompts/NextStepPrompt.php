<?php

namespace App\Services\AI\Prompts;

use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\Exceptions\AIInvalidResponseException;

class NextStepPrompt extends BasePrompt
{
    private array $userState = [];
    private array $analysis = [];

    public function name(): string
    {
        return 'next_step';
    }

    public function recommendedModel(): ?string
    {
        return null;
    }

    public function temperature(): float
    {
        return 0.5; // نحتاج بعض الإبداع لاقتراح خطوات محددة
    }

    // ==========================================================
    // Setters
    // ==========================================================

    public function withUserState(array $state): self
    {
        $this->userState = $state;
        return $this;
    }

    public function withAnalysis(array $analysis): self
    {
        $this->analysis = $analysis;
        return $this;
    }

    // ==========================================================
    // Schema
    // ==========================================================

    public function schema(): ?array
    {
        return [
            'primary' => ['type' => 'object'],
            'secondary' => ['type' => 'array', 'items' => 'object'],
            'explanation' => ['type' => 'string'],
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

        // primary موجود
        if (empty($data['primary']) || !is_array($data['primary'])) {
            $errors[] = 'primary مطلوب';
        } else {
            $primary = $data['primary'];
            $requiredPrimary = ['title', 'reason', 'why', 'outcome', 'steps', 'completion_criteria', 'effort', 'time'];
            foreach ($requiredPrimary as $field) {
                if (empty($primary[$field])) {
                    $errors[] = "primary.{$field} مطلوب";
                }
            }

            // steps يجب أن تكون قائمة
            if (isset($primary['steps']) && (!is_array($primary['steps']) || count($primary['steps']) < 2)) {
                $errors[] = 'primary.steps يجب أن تكون قائمة بـ 2 عناصر على الأقل';
            }

            // effort من القيم المسموحة
            if (isset($primary['effort']) && !in_array($primary['effort'], ['low', 'medium', 'high'])) {
                $errors[] = 'primary.effort يجب أن يكون: low أو medium أو high';
            }
        }

        // secondary: بالضبط 2
        if (!isset($data['secondary']) || !is_array($data['secondary'])) {
            $errors[] = 'secondary مطلوب';
        } elseif (count($data['secondary']) !== 2) {
            $errors[] = 'secondary يجب أن يحتوي على خطوتين بالضبط';
        }

        // explanation
        if (empty($data['explanation'])) {
            $errors[] = 'explanation مطلوب';
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
أنت مساعد في تطبيق NEXT. مهمتك: اقتراح الخطوة التالية الواحدة للمستخدم.

القواعد الصارمة (لا تكسرها):

1. **خطوة واحدة فقط** كـ primary
   - ليست قائمة مهام
   - ليست خطة كبيرة
   - شيء واحد يمكن إنجازه في جلسة واحدة أو جلستين

2. **يجب أن تكون الخطوة**:
   - قابلة للتنفيذ (action-oriented، تبدأ بفعل)
   - محددة (لا "طوّر مهاراتك" — بل "اكتب قائمة بـ 10 مهارات مطلوبة")
   - ضمن مساحة تأثير المستخدم (controllable)
   - مرتبطة مباشرة بالصورة الحالية
   - لها مخرج ملموس

3. **الخطوة يجب أن تُخرج شيئًا**:
   - ملف، قائمة، قرار، مسودة، محادثة، رسم بياني...
   - ليس "فهم" أو "تفكير" فقط

4. **لا تقدم وعودًا**:
   - "ستحصل على..."، "ستنجح..."
   - لا تخترع شروط قبول
   - لا تعطي نسب نجاح

5. **خطوتان secondary**:
   - للسياق فقط
   - عنوان قصير + وصف بجملة
   - لا تفاصيل (لأنها للاحقًا)

6. **اللغة**: عربية فصحى بسيطة

7. **الأسلوب**:
   - مباشر، بلا مبالغة
   - عملي، بلا شعارات
   - واقعي، بلا وعود
PROMPT;
    }

    private function buildUserPrompt(): string
    {
        $goal = $this->formatGoal();
        $analysis = $this->formatAnalysis();

        return <<<PROMPT
## هدف المستخدم

{$goal}

## الصورة الحالية (التحليل)

{$analysis}

## مهمتك

اقترح الخطوة التالية للمستخدم بناءً على الصورة الحالية.

أعد JSON فقط بهذا الشكل:

{
  "primary": {
    "title": "عنوان قصير للخطوة (فعل + نتيجة، حتى 80 حرف)",
    "reason": "لماذا هذه الخطوة الآن؟ (جملة واحدة، حتى 150 حرف)",
    "why": "الشرح الأعمق: لماذا هي الأنسب؟ (2-3 جمل، حتى 400 حرف)",
    "outcome": "ما الذي سيخرج به المستخدم فعليًا؟ (جملة، حتى 200 حرف)",
    "steps": [
      "الخطوة الفرعية 1 (تبدأ بفعل)",
      "الخطوة الفرعية 2",
      "الخطوة الفرعية 3",
      "الخطوة الفرعية 4 (اختياري)"
    ],
    "completion_criteria": "كيف يعرف المستخدم أنه أنجز الخطوة؟ (جملة، حتى 200 حرف)",
    "effort": "low|medium|high",
    "time": "تقدير الوقت (مثال: 30-45 دقيقة، ساعتان، 3 ساعات)"
  },
  "secondary": [
    {
      "title": "عنوان الخطوة الثانية (قصير)",
      "text": "وصف بجملة واحدة (حتى 150 حرف)"
    },
    {
      "title": "عنوان الخطوة الثالثة (قصير)",
      "text": "وصف بجملة واحدة (حتى 150 حرف)"
    }
  ],
  "explanation": "لماذا هذه الخطوات معًا؟ (2-3 جمل، حتى 400 حرف)"
}

## معايير الجودة

- **primary.title**: يجب أن تبدأ بفعل إجرائي (اكتب، حلل، ابحث، ابنِ، صمم...)
- **primary.steps**: 3-4 خطوات فرعية، كل واحدة واضحة
- **secondary**: عناوين قصيرة، ليس خطوات كاملة
- **explanation**: توضح المنطق، ليس تكرار

## أمثلة على خطوات جيدة

- "حلل 5 إعلانات وظائف frontend واكتب المهارات المتكررة"
- "ابنِ صفحة هبوط بسيطة بـ React وارفعها على GitHub Pages"
- "اكتب بريدًا لـ 3 مطورين على LinkedIn لسؤالهم عن السوق"
- "أنشئ قائمة بـ 10 شركات محلية تستهدفها وحدد متطلباتها"

## أمثلة على خطوات سيئة (لا تفعلها)

- ❌ "طوّر مهاراتك" (عامة جدًا)
- ❌ "تعلم React" (ليست خطوة، بل هدف)
- ❌ "ابنِ مشروعًا كاملًا" (ضخمة جدًا)
- ❌ "فكر في أهدافك" (لا مخرج ملموس)
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

        return sprintf(
            "النوع: %s\nالعنوان: %s",
            $goal['type'] ?? 'غير محدد',
            $goal['title'] ?? 'غير محدد'
        );
    }

    private function formatAnalysis(): string
    {
        if (empty($this->analysis)) {
            return '(لا يوجد تحليل)';
        }

        $lines = [];

        $lines[] = "**التشخيص**: " . ($this->analysis['headline'] ?? '—');
        $lines[] = "";
        $lines[] = "**الملخص**: " . ($this->analysis['summary'] ?? '—');
        $lines[] = "";

        $sections = [
            'known' => 'ما يعرفه',
            'unknown' => 'ما لا يعرفه',
            'controllable' => 'ما يمكنه التأثير فيه',
            'uncontrollable' => 'ما هو خارج سيطرته',
        ];

        foreach ($sections as $key => $label) {
            if (!empty($this->analysis[$key]) && is_array($this->analysis[$key])) {
                $lines[] = "**{$label}**:";
                foreach ($this->analysis[$key] as $item) {
                    $lines[] = "- {$item}";
                }
                $lines[] = "";
            }
        }

        return implode("\n", $lines);
    }
}