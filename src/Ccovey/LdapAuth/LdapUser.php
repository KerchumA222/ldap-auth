<?php namespace Ccovey\LdapAuth;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Config;
use Illuminate\Auth;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Description of LdapUser
 *
 * @author ccovey
 */
class LdapUser extends Model implements AuthenticatableContract
{
	use Authenticatable;
	public $ldap_attributes = [];
    public $ldap_persistent = [];

    /**
	 * Get the unique identifier for the user.
	 *
	 * @return mixed
	 */
    public function getUsername()
	{
		$username = (Config::has('auth.username_field')) ? Config::get('auth.username_field') : 'username';
		return $this->{$username};
	}

    /**
	 * Get the password for the user.
	 *
	 * @return string
	 */
	public function getPassword()
	{
		return $this->attributes['password'];
	}

	/**
	 * Returns the roles granted to the user.
	 *
	 * <code>
	 * public function getRoles()
	 * {
	 *     return array('ROLE_USER');
	 * }
	 * </code>
	 *
	 * Alternatively, the roles might be stored on a ``roles`` property,
	 * and populated in any number of different ways when the user object
	 * is created.
	 *
	 * @return Role[] The user roles
	 */
	public function getRoles()
	{
		// TODO: Implement getRoles() method.
	}

	/**
	 * Returns the salt that was originally used to encode the password.
	 *
	 * This can return null if the password was not encoded using a salt.
	 *
	 * @return string|null The salt
	 */
	public function getSalt()
	{
		return null;
	}


	/**
	 * Removes sensitive data from the user.
	 *
	 * This is important if, at any given point, sensitive information like
	 * the plain-text password is stored on this object.
	 */
	public function eraseCredentials()
	{
		// TODO: Implement eraseCredentials() method.
	}
}
