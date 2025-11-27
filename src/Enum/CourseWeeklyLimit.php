<?php

namespace App\Enum;

enum CourseWeeklyLimit: int
{
    case FIRST_YEAR = 15;
    case SECOND_YEAR = 14;
    case THIRD_YEAR = 13;
    case FOURTH_YEAR = 12;

    public static function getLimitByCourseNumber(int $courseNumber): int
    {
        return match($courseNumber) {
            1 => self::FIRST_YEAR->value,
            2 => self::SECOND_YEAR->value,
            3 => self::THIRD_YEAR->value,
            4 => self::FOURTH_YEAR->value,
            default => self::FOURTH_YEAR->value
        };
    }
}
