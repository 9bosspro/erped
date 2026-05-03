<?php

namespace Core\Base\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Iscitizens implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    /* public function __construct($multiplier)
    {
        $this->multiplier = $multiplier;
    } */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        //
        if (! is_scalar($value) || ! check_citizen_id((string) $value)) {
            $fail('The :attribute จำเป้นต้องเป็นเลขประจำตัวประชาชน');
        }
    }
}
