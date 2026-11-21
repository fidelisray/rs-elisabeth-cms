<?php

namespace App\Enums;

enum DynamicFormMode: string
{
    case Questionnaire = 'questionnaire';
    case Feedback = 'feedback';

    public function label(): string
    {
        return match ($this) {
            self::Questionnaire => 'Kuesioner / Survei',
            self::Feedback => 'Kritik & Saran',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Questionnaire->value => self::Questionnaire->label(),
            self::Feedback->value => self::Feedback->label(),
        ];
    }
}
