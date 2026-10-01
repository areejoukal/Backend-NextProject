<?php

namespace Tests\Feature;

use App\Services\AI\Prompts\AdaptiveQuestionPrompt;
use Tests\TestCase;

class TestAdaptivePrompt extends TestCase
{
    public function test_it_selects_first_question(): void
    {
        $prompt = app(AdaptiveQuestionPrompt::class)
            ->withUserState([
                'goal' => [
                    'type' => 'employment',
                    'title' => 'أبحث عن وظيفة frontend',
                ],
                'profile' => [
                    'experience' => 'learning',
                    'education' => 'bachelor',
                    'field' => 'تطوير الويب',
                    'location' => 'غزة',
                ],
            ])
            ->withPreviousAnswers([])
            ->withAvailableQuestions([
                ['id' => 'q_goal_clarity', 'text' => 'ما أكثر شيء غير واضح لك بخصوص هدفك؟'],
                ['id' => 'q_skills_gap', 'text' => 'ما المهارات التي تشعر أنك تفتقدها؟'],
                ['id' => 'q_timeline', 'text' => 'ما الجدول الزمني الذي تفكر فيه؟'],
                ['id' => 'q_experience_level', 'text' => 'كم مشروعًا حقيقيًا بنيت حتى الآن؟'],
                ['id' => 'q_target_companies', 'text' => 'هل لديك شركات محددة تستهدفها؟'],
            ]);

        $result = $prompt->generate();

        echo "\n=== TEST 1: First question ===\n";
        print_r($result->data);
        echo "\nTokens: " . $result->tokens() . "\n";
        echo "Latency: " . $result->latencyMs() . " ms\n";
        echo "Model: " . $result->model() . "\n";

        $this->assertTrue($result->isJson());
        $this->assertArrayHasKey('question_id', $result->data);
    }

    public function test_it_selects_next_question(): void
    {
        $prompt = app(AdaptiveQuestionPrompt::class)
            ->withUserState([
                'goal' => ['type' => 'employment', 'title' => 'أبحث عن وظيفة frontend'],
                'profile' => ['experience' => 'learning', 'field' => 'تطوير الويب'],
            ])
            ->withPreviousAnswers([
                [
                    'question_id' => 'q_goal_clarity',
                    'question_text' => 'ما أكثر شيء غير واضح لك بخصوص هدفك؟',
                    'answer' => 'ما المطلوب مني أصلًا؟',
                ],
            ])
            ->withAvailableQuestions([
                ['id' => 'q_skills_gap', 'text' => 'ما المهارات التي تشعر أنك تفتقدها؟'],
                ['id' => 'q_timeline', 'text' => 'ما الجدول الزمني الذي تفكر فيه؟'],
                ['id' => 'q_experience_level', 'text' => 'كم مشروعًا حقيقيًا بنيت حتى الآن؟'],
                ['id' => 'q_target_companies', 'text' => 'هل لديك شركات محددة تستهدفها؟'],
            ]);

        $result = $prompt->generate();

        echo "\n=== TEST 2: Next question ===\n";
        print_r($result->data);
        echo "\nTokens: " . $result->tokens() . "\n";
        echo "Latency: " . $result->latencyMs() . " ms\n";

        $this->assertTrue($result->isJson());
    }

    public function test_it_stops_when_enough_info(): void
    {
        $prompt = app(AdaptiveQuestionPrompt::class)
            ->withUserState([
                'goal' => ['type' => 'employment', 'title' => 'أبحث عن وظيفة frontend'],
                'profile' => ['experience' => 'some_experience', 'field' => 'تطوير الويب'],
            ])
            ->withPreviousAnswers([
                ['question_id' => 'q1', 'question_text' => 'الغامض؟', 'answer' => 'المتطلبات'],
                ['question_id' => 'q2', 'question_text' => 'المهارات الناقصة؟', 'answer' => 'React'],
                ['question_id' => 'q3', 'question_text' => 'كم مشروعًا؟', 'answer' => '3'],
                ['question_id' => 'q4', 'question_text' => 'الجدول الزمني؟', 'answer' => '3 أشهر'],
                ['question_id' => 'q5', 'question_text' => 'شركات محددة؟', 'answer' => 'نعم'],
                ['question_id' => 'q6', 'question_text' => 'portfolio؟', 'answer' => 'نعم'],
                ['question_id' => 'q7', 'question_text' => 'CV؟', 'answer' => 'جاهز'],
            ])
            ->withAvailableQuestions([
                ['id' => 'q_extra1', 'text' => 'سؤال إضافي 1'],
                ['id' => 'q_extra2', 'text' => 'سؤال إضافي 2'],
            ]);

        $result = $prompt->generate();

        echo "\n=== TEST 3: Should stop ===\n";
        print_r($result->data);
        echo "\nTokens: " . $result->tokens() . "\n";

        $this->assertTrue($result->isJson());
    }
}