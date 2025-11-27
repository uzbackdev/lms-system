<?php

namespace App\Enum;

enum PersonType :string
{
    case STUDENT = 'student';

    case TEACHER = 'teacher';

    case ADMIN = 'admin';
}
