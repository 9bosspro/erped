<?php

namespace Core\Base\Rules;

use Exception;
use Illuminate\Contracts\Validation\Rule;

class EmailSpam implements Rule
{
    /**
     * Create a new rule instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        //
        if (app()->environment('local')) {
            return true;
        }

        return ! config('services.mailboxlayer.key') || $this->check($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Invalid email address.';
    }

    /**
     * Perform email check.
     */
    protected function check(string $email): bool
    {
        try {
            $response = file_get_contents('https://apilayer.net/api/check?'.http_build_query([
                'access_key' => config('services.mailboxlayer.key'),
                'email' => '[mailbox-layer-account-email]',
                'smtp' => 1,
            ]));

            $response = json_decode($response, true);

            return $response['format_valid'] && ! $response['disposable'];
        } catch (Exception $exception) {
            report($exception);

            if (app()->environment('local')) {
                return false;
            }

            // Don't block production environment in case of apilayer error
            return true;
        }
    }
}
