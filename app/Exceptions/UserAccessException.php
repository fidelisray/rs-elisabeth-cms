<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar saat sebuah perubahan akan melanggar aturan akses user,
 * misalnya menonaktifkan satu-satunya super_admin.
 */
class UserAccessException extends RuntimeException {}
