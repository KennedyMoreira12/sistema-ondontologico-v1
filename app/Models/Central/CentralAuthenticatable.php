<?php

namespace App\Models\Central;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * `CentralModel` com o mesmo stack de traits de
 * `Illuminate\Foundation\Auth\User`, para os models que precisam autenticar.
 *
 * Existe porque trocar `Authenticatable` por `CentralModel` direto derrubaria
 * `Authenticatable`, `Authorizable`, `CanResetPassword` e `MustVerifyEmail` de
 * uma vez. A conexão continua fixada em `central` pela classe pai.
 */
abstract class CentralAuthenticatable extends CentralModel implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract
{
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail;
}
