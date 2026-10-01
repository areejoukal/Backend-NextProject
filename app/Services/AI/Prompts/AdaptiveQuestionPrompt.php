<?php

namespace App\Services\AI\Prompts;

use App\Services\AI\AIService;
use App\Services\AI\DTOs\AIResponse;

class AdaptiveQuestionPrompt
{
    private array $userState = [];
    private array $previousAnswers = [];
    private int $questionNumber = 1;
    private int $maxQuestions = 8;

    /**
     * عندما يكون true:
     * يمنع الـ AI من إنهاء التقييم.
     */
    private bool $mustContinue = false;

    public function __construct(
        private readonly AIService $ai,
    ) {}

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

    public function withProgress(int $current, int $max): self
    {
        $this->questionNumber = $current;
        $this->maxQuestions = $max;

        return $this;
    }

    /**
     * إجبار الـ AI على الاستمرار.
     */
    public function forceContinue(bool $value = true): self
    {
        $this->mustContinue = $value;

        return $this;
    }

    /**
     * توليد السؤال التالي.
     */
    public function generate(): AIResponse
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt();

        /*
         * نستخدم chatJsonResponse بدل chat
         * حتى يتم تحليل استجابة الـ AI ووضعها
         * داخل $response->json.
         */
        return $this->ai->chatJsonResponse(
            userPrompt: $userPrompt,
            schema: $this->getResponseSchema(),
            systemPrompt: $systemPrompt,
            purpose: 'adaptive_question_generation',
            temperature: 0.7,
        );
    }

    // ==========================================================
    // Private
    // ==========================================================

    /**
     * Schema الخاص باستجابة السؤال التكيفي.
     *
     * ملاحظة:
     * AIService / AIRequest مسؤولان عن تفعيل JSON.
     * هذا الـ schema يصف الشكل المطلوب للاستجابة.
     */
    private function getResponseSchema(): array
    {
        return [
            'type' => 'object',

            'properties' => [
                'should_stop' => [
                    'type' => 'boolean',
                ],

                'question' => [
                    'type' => [
                        'object',
                        'null',
                    ],

                    'properties' => [
                        'id' => [
                            'type' => 'string',
                        ],

                        'text' => [
                            'type' => 'string',
                        ],

                        'type' => [
                            'type' => 'string',
                            'enum' => [
                                'single_choice',
                                'multi_choice',
                                'scale',
                                'short_text',
                            ],
                        ],

                        'options' => [
                            'type' => [
                                'array',
                                'null',
                            ],
                            'items' => [
                                'type' => 'string',
                            ],
                        ],

                        'reason' => [
                            'type' => 'string',
                        ],

                        'expected_info_gain' => [
                            'type' => 'number',
                        ],
                    ],

                    'required' => [
                        'id',
                        'text',
                        'type',
                        'options',
                        'reason',
                        'expected_info_gain',
                    ],
                ],

                'reason' => [
                    'type' => [
                        'string',
                        'null',
                    ],
                ],
            ],

            'required' => [
                'should_stop',
                'question',
            ],
        ];
    }

    private function buildSystemPrompt(): string
    {
        $minimumRule = $this->mustContinue
            ? <<<RULE
- ممنوع تمامًا استخدام should_stop = true.
- يجب أن يكون should_stop = false.
- يجب أن يحتوي الرد على question صالح.
- يجب توليد سؤال جديد حتى لو بدا أن المعلومات المتوفرة كافية.
RULE
            : <<<RULE
- يمكن استخدام should_stop = true إذا كانت المعلومات كافية فعلًا.
- إذا لم تكن المعلومات كافية، يجب الاستمرار في طرح الأسئلة.
RULE;

        return <<<PROMPT
أنت خبير في تصميم أسئلة التقييم التكيفي (Adaptive Assessment).

مهمتك هي توليد السؤال التالي المناسب للمستخدم بناءً على:

1. حالته الحالية.
2. هدفه.
3. ملفه الشخصي.
4. إجاباته السابقة.

قواعد صارمة:

- السؤال يجب أن يكون قصيرًا، أقل من 20 كلمة.
- السؤال يجب أن يكون مباشرًا وواضحًا.
- استخدم العربية الفصحى المبسطة.
- السؤال يجب أن يكون محددًا وليس عامًا.
- لا تكرر سؤالًا تم طرحه سابقًا.
- ابنِ على الإجابات السابقة.
- لا تسأل عن معلومة موجودة بالفعل في الإجابات السابقة.
- الهدف هو الحصول على أكبر قدر مفيد من المعلومات بأقل عدد ممكن من الأسئلة.
- ركز على فهم حالة المستخدم الفعلية.
- لا تخترع معلومات غير موجودة.
- لا تفترض مهارات أو خبرات لم يذكرها المستخدم.

قاعدة الإكمال:

- الحد الأدنى للتقييم هو 3 إجابات.
- لا يجوز إنهاء التقييم قبل الوصول إلى 3 إجابات.
- إذا كان عدد الإجابات السابقة أقل من 3، يجب توليد سؤال جديد.
- بعد الوصول إلى 3 إجابات أو أكثر، يمكن التوقف فقط إذا كانت المعلومات كافية فعلًا.
- إذا كانت هناك معلومات مهمة ناقصة، استمر في طرح الأسئلة.
- لا تتجاوز الحد الأقصى للأسئلة المسموح به.

{$minimumRule}

يجب أن تكون الاستجابة JSON فقط.

عند الاستمرار:

{
  "should_stop": false,
  "question": {
    "id": "q_generated_1",
    "text": "نص السؤال هنا",
    "type": "single_choice",
    "options": [
      "خيار 1",
      "خيار 2",
      "خيار 3"
    ],
    "reason": "لماذا اخترت هذا السؤال",
    "expected_info_gain": 0.7
  }
}

عند السماح بالتوقف:

{
  "should_stop": true,
  "question": null,
  "reason": "سبب التوقف"
}

لا تضع أي نص خارج JSON.
PROMPT;
    }

    private function buildUserPrompt(): string
    {
        $goalInfo = $this->formatGoal();
        $profileInfo = $this->formatProfile();
        $answersInfo = $this->formatAnswers();

        $answersCount = count($this->previousAnswers);

        $progress =
            "السؤال {$this->questionNumber} من {$this->maxQuestions}";

        if ($answersCount < 3) {
            $remaining = 3 - $answersCount;

            $minimumRule = <<<TEXT

# قاعدة الإكمال الإلزامية

عدد الإجابات السابقة: {$answersCount}
الحد الأدنى المطلوب: 3 إجابات
المتبقي للوصول إلى الحد الأدنى: {$remaining}

ممنوع إنهاء التقييم الآن.

يجب أن يكون:

should_stop = false

ويجب أن تعيد:

question صالحًا ومحددًا.

لا تستخدم should_stop = true تحت أي ظرف في هذه المرحلة.
TEXT;
        } else {
            $minimumRule = <<<TEXT

# حالة الحد الأدنى

عدد الإجابات السابقة: {$answersCount}

تم الوصول إلى الحد الأدنى المطلوب وهو 3 إجابات.

يمكنك استخدام should_stop = true فقط إذا كانت المعلومات المتوفرة كافية فعلًا لفهم حالة المستخدم.

إذا كانت هناك معلومات مهمة ناقصة، استمر في طرح سؤال جديد.
TEXT;
        }

        return <<<PROMPT
# معلومات الهدف

{$goalInfo}

# معلومات المستخدم

{$profileInfo}

# الإجابات السابقة

{$answersInfo}

# التقدم

{$progress}

{$minimumRule}

# مهمتك

ولّد السؤال التالي المناسب.

تذكّر:

- لا تكرر أي سؤال سابق.
- ابنِ على الإجابات السابقة.
- لا تسأل عن معلومات موجودة بالفعل.
- اجعل السؤال مفيدًا لفهم حالة المستخدم.
- ركز على ما يعرفه المستخدم وما لا يعرفه وما يستطيع فعله حاليًا.
- لا تجعل السؤال عامًا أو نظريًا بلا فائدة عملية.
- لا تخترع أي معلومات.
- يجب أن تكون الاستجابة JSON فقط.
PROMPT;
    }

    private function formatGoal(): string
    {
        $goal = $this->userState['goal'] ?? [];

        $lines = [
            "- النوع: " . ($goal['type'] ?? 'غير محدد'),
            "- العنوان: " . ($goal['title'] ?? 'غير محدد'),
        ];

        if (!empty($goal['details'])) {
            $lines[] = "- التفاصيل: " . json_encode(
                $goal['details'],
                JSON_UNESCAPED_UNICODE
            );
        }

        return implode("\n", $lines);
    }

    private function formatProfile(): string
    {
        $profile = $this->userState['profile'] ?? [];

        $lines = [];

        foreach (
            [
                'age',
                'location',
                'education',
                'field',
                'experience',
                'background',
            ] as $key
        ) {
            if (!empty($profile[$key])) {
                $lines[] = "- {$key}: {$profile[$key]}";
            }
        }

        return $lines
            ? implode("\n", $lines)
            : '- لا توجد معلومات إضافية';
    }

    private function formatAnswers(): string
    {
        if (empty($this->previousAnswers)) {
            return '- لا توجد إجابات بعد (هذا أول سؤال)';
        }

        $lines = [];

        foreach ($this->previousAnswers as $i => $a) {
            $num = $i + 1;

            $q = $a['question_text']
                ?? $a['question_id']
                ?? '—';

            $ans = is_array($a['answer'] ?? null)
                ? json_encode(
                    $a['answer'],
                    JSON_UNESCAPED_UNICODE
                )
                : ($a['answer'] ?? '—');

            $lines[] = "{$num}. س: {$q}\n   ج: {$ans}";
        }

        return implode("\n", $lines);
    }
}