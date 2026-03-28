<?php

namespace Core\Base\Traits;

// $RECYCLE.BIN

trait EncryptableTrait
{
    /**
     * If the attribute is in the encryptable array
     * then decrypt it.
     *
     *
     * @return $value
     * namespace App\Models;

            use Illuminate\Database\Eloquent\Model;
            use App\Traits\Encryptable;
            class UserSalary extends Model
            {
                use Encryptable;
                protected $fillable = [
                    'user_id',
                    'payroll',
                    'start_at',
                    'end_at',
                ];
                protected $encryptable = [
                    'payroll',
                ];
            }
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if (isset($this->encryptable) && in_array($key, $this->encryptable) && $value !== '') {
            $value = decrypt($value);
        }

        return $value;
    }

    /**
     * If the attribute is in the encryptable array
     * then encrypt it.
     */
    public function setAttribute($key, $value)
    {
        if (isset($this->encryptable) && in_array($key, $this->encryptable)) {
            $value = encrypt($value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * When need to make sure that we iterate through
     * all the keys.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();
        if (isset($this->encryptable)) {
            foreach ($this->encryptable as $key) {
                if (isset($attributes[$key])) {
                    $attributes[$key] = decrypt($attributes[$key]);
                }
            }

            return $attributes;
        }
    }
}
