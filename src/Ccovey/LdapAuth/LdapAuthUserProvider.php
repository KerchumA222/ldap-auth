<?php
namespace Ccovey\LdapAuth;

use Adldap\Adldap;
use Exception;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Symfony\Component\Debug\Exception\ClassNotFoundException;

/**
 * Class to build array to send to GenericUser
 * This allows the fields in the array to be
 * accessed through the Auth::user() method
 */
class LdapAuthUserProvider implements UserProvider
{
	/**
	 * Active Directory Object
	 *
	 * @var \Adldap\Adldap
	 */
	protected $ad;

	/**
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * DI in adLDAP object for use throughout
	 *
	 * @param \Adldap\Adldap $ad
	 * @param array $config
	 * @param string $model
	 */
	public function __construct(Adldap $ad, array $config = null, $model = '')
	{
		$this->ad = $ad;

		$this->config = $config;

		$this->model = $model;
	}

	/**
	 * Retrieve a user by their unique identifier.
	 *
	 * @param  mixed  $identifier
	 * @param  mixed  $ldapIdentifier; default to null
	 *
	 * @return \Illuminate\Auth\GenericUser|null
	 */
	public function retrieveByID($identifier, $ldapIdentifier = null)
	{
		$model = $this->createModel();
		$user = $model->newQuery()
			->find($identifier);
		if(is_null($user)){
			return null;
		}
        return $this->getUserFromLDAP($user, $ldapIdentifier);
	}

	/**
	 * Retrieve a user by by their unique identifier and "remember me" token.
	 *
	 * @param  mixed  $identifier
	 * @param  string  $token
	 * @return Model|null
	 */
	public function retrieveByToken($identifier, $token)
	{
        $model = $this->createModel();
        $model = $model->newQuery()
                ->where($this->getIdentifierField(), $identifier)
                ->where($model->getRememberTokenName(), $token)
                ->first();
		return $this->getUserFromLDAP($model);
	}

	/**
	 * Update the "remember me" token for the given user in storage.
	 *
	 * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
	 * @param  string  $token
	 * @return void
	 * @throws Exception
	 */
	public function updateRememberToken(Authenticatable $user, $token)
	{
		$user->setRememberToken($token);
		if (is_a($user, '\Ardent')) {
			$user->forceSave();
		}
		else {
			$user->save();
		}
	}

	/**
	 * Retrieve a user by the given credentials.
	 *
	 * @param  array  $credentials
	 * @return Model|null
	 */
	public function retrieveByCredentials(array $credentials)
	{
		if (! $user = $credentials[$this->getUsernameField()]) {
			throw new \InvalidArgumentException('"'.$this->getUsernameField().'" field is missing');
		}

		$query = $this->createModel()->newQuery();

		foreach ($credentials as $key => $value)
		{
			if ( ! str_contains($key, 'password')) $query->where($key, $value);
		}

		$model = $query->first();
		if($model) {
			return $this->getUserFromLDAP($model);
		}

		return null;
	}

	/**
	 * Validate a user against the given credentials.
	 *
	 * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
	 * @param  array  $credentials
	 * @return bool
	 */

	public function validateCredentials(Authenticatable $user, array $credentials)
	{
		return $this->ad->authenticate($credentials['username'], $credentials['password']);
	}

	/**
	 * Build the array sent to GenericUser for use in Auth::user()
	 *
	 * @param \Adldap\Adldap $infoCollection
	 * @return array $info
	 */
	protected function setInfoArray($infoCollection)
	{
		/*
		* in app/auth.php set the fields array with each value
		* as a field you want from active directory
		* If you have 'user' => 'samaccountname' it will set the $info['user'] = $infoCollection->samaccountname
		* refer to the adLDAP docs for which fields are available.
		*/
		$info = [];
		if ( ! empty($this->config['fields'])) {
			foreach ($this->config['fields'] as $k => $field) {
				if ($k == 'groups') {
					$info[$k] = $this->getAllGroups($infoCollection->memberof);
				}elseif ($k == 'primarygroup') {
					$info[$k] = $this->getPrimaryGroup($infoCollection->distinguishedname);
				}else{
					$info[$k] = $infoCollection->$field;
				}
			}
		}else{
			//if no fields array present default to username and displayname
			$info['username'] = $infoCollection->samaccountname[0];
			$info['displayname'] = $infoCollection->displayname[0];
			$info['primarygroup'] = $this->getPrimaryGroup($infoCollection->distinguishedname);
			$info['groups'] = $this->getAllGroups($infoCollection->memberof);
		}
		/*
		* I needed a user list to populate a dropdown
		* Set userlist to true in app/config/auth.php and set a group in app/config/auth.php as well
		* The table is the OU in Active directory you need a list of.
		*/
		if ( ! empty($this->config['userList'])) {
			$info['userlist'] = $this->ad->folder()->listing(array($this->config['group']));
		}
		return $info;
	}



	/**
	 * Add Ldap fields to current user model.
	 *
	 * @param Model $model
	 * @param array $ldap
	 * @return LdapUser
	 */
	protected function addLdapToModel($model, $ldap)
	{
        if(is_a($model, '\Ccovey\LdapAuth\LdapUser')){
            $model->ldap_attributes = $ldap;
        }
        if(!empty($model->ldap_persistent)) {
            foreach ($model->ldap_persistent as $key => $value) {
                if (!is_string($key)) {
                    $key = $value;
                }
                if (!is_null($ldap[$value])) {
                    $model->{$key} = $ldap[$value];
                }
            }
            $model->save();
        }
		return $model;
	}

	/**
	 * Return Primary Group Listing
	 * @param  array $groupList
	 * @return string
	 */
	protected function getPrimaryGroup($groupList)
	{
		$groups = explode(',', $groupList[0]);

		return substr($groups[1], '3');
	}

	/**
	 * Return list of groups (except domain and suffix)
	 * @param  array $groups
	 * @return array
	 */
	protected function getAllGroups($groups)
	{
		$grps = [];
		if ( ! is_null($groups) ) {
			if (!is_array($groups)) {
				$groups = explode(',', $groups);
			}
			foreach ($groups as $k => $group) {
				$splitGroups = explode(',', $group);
				foreach ($splitGroups as $splitGroup) {
					if (substr($splitGroup,0, 3) !== 'DC=') {
						$groupName = substr($splitGroup, '3');
						if($groupName && !in_array($groupName, $grps)){
							$grps[] = $groupName;
						}
					}
				}
			}
		}

		return $grps;
	}
	/**
	 * Create a new instance of the model.
	 *
	 * @return \Illuminate\Contracts\Auth\Authenticatable
     * @throws ClassNotFoundException
	 */
	public function createModel()
	{
		$class = '\\'.ltrim($this->model, '\\');
		return new $class;
	}

    /**
     * @return string
     */
    public function getModel()
	{
		return $this->model;
	}

    /**
     * @return string
     */
    protected function getUsernameField()
	{
		return isset($this->config['username_field'])?$this->config['username_field']:'username';
	}

	/**
     * @return string
     */
    protected function getIdentifierField()
	{
		return isset($this->config['identifier_field'])?$this->config['identifier_field']:'id';
	}


    /**
     * @param $ldapIdentifier
     * @param $user
     * @return LdapUser|null
     */
    public function getUserFromLDAP($user, $ldapIdentifier = null)
    {
        if (is_null($ldapIdentifier)) {
            $ldapIdentifier = $user->{$this->getUsernameField()};
        }

        $ldapUserInfo = Session::get('ldapUserInfo', function () use ($ldapIdentifier) {
            $infoCollection = $this->ad->users()->find($ldapIdentifier, ['*']);
            if ($infoCollection) {
                $info = $this->setInfoArray($infoCollection);
                Session::put('ldapUserInfo', $info);
                return $info;
            }
            return null;
        });

        if ($ldapUserInfo) {
            return $this->addLdapToModel($user, $ldapUserInfo);
        }
        else {
            return null;
        }
    }
}
