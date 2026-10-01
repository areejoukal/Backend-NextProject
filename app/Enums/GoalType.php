<?php

namespace App\Enums;

enum GoalType: string
{
    case Academic = 'academic';
    case Employment = 'employment';
    case CareerGrowth = 'career_growth';
    case CareerChange = 'career_change';
    case Skills = 'skills';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'الدراسة والماجستير',
            self::Employment => 'الحصول على وظيفة',
            self::CareerGrowth => 'تطوير مساري المهني',
            self::CareerChange => 'تغيير مجالي',
            self::Skills => 'تطوير مهاراتي',
            self::Other => 'هدف مختلف',
        };
    }
}