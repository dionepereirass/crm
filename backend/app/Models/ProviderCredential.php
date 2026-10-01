<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class ProviderCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'encrypted_credentials',
    ];

    protected $hidden = [
        'encrypted_credentials',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Define credenciais criptografadas de forma segura.
     */
    public function setCredentials(array $credentials): void
    {
        $this->encrypted_credentials = Crypt::encryptString(json_encode($credentials));
    }

    /**
     * Recupera as credenciais descriptografadas para uso exclusivo pelo driver no backend.
     */
    public function getDecryptedCredentials(): array
    {
        if (empty($this->encrypted_credentials)) {
            return [];
        }

        try {
            $decrypted = Crypt::decryptString($this->encrypted_credentials);
            return json_decode($decrypted, true) ?: [];
        } catch (Throwable) {
            return [];
        }
    }
}
