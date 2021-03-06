<?php
namespace Ccovey\LdapAuth;

use Adldap\Adldap;
use Adldap\Connections\Configuration;
use Exception;
use Illuminate\Auth\Guard;
use Illuminate\Auth\AuthManager;

/**
 * Class LdapAuthManager
 * @package Ccovey\LdapAuth
 */
class LdapAuthManager extends AuthManager
{
    /**
     * 
     * @return \Illuminate\Auth\Guard
     */
    protected function createLdapDriver()
    {
        $provider = $this->createLdapProvider();
        
        return new Guard($provider, $this->app['session.store']);
    }
    
    /**
     * 
     * @return \Illuminate\Contracts\Auth\UserProvider
     */
    protected function createLdapProvider()
    {
        $ad = new Adldap($this->getLdapConfig());

        $model = null;
        
        if ($this->app['config']['auth.model']) {
            $model = $this->app['config']['auth.model'];
        }
        
        return new LdapAuthUserProvider($ad, $this->getAuthConfig(), $model);
    }

	/**
	 * @return mixed
	 * @throws MissingAuthConfigException
	 */
	protected function getAuthConfig()
    {
        if ( ! is_null($this->app['config']['auth']) ) {
            return $this->app['config']['auth'];
        }
        throw new MissingAuthConfigException;
    }

	/**
	 * @return \Adldap\Connections\Configuration
	 */
	protected function getLdapConfig()
    {
	    $config = new Configuration();

		$config->setAccountSuffix(env('LDAP_ACCOUNT_SUFFIX'));
        $config->setDomainControllers(explode('|',env('LDAP_DOMAIN_CONTROLLERS')));
	    $config->setBaseDn(env('LDAP_BASE_DN'));
	    $config->setAdminUsername(env('LDAP_ADMIN_USERNAME'));
	    $config->setAdminPassword(env('LDAP_ADMIN_PASSWORD'));
	    env('LDAP_USE_SSL', false)?$config->setUseSSL(true):null;
	    env('LDAP_USE_TLS', false)?$config->setUseTLS(true):null;
	    $config->setPersonFilter(['category'=>env('LDAP_PERSON_CATEGORY', 'objectClass'), 'person'=>env('LDAP_PERSON_TYPE', 'person'), ]);
	    $config->setFollowReferrals(1);
	    //$config->setUseSSO(true);

	    return $config;
    }
}

/**
* MissingAuthConfigException
*/
class MissingAuthConfigException extends Exception
{

	/**
	 *
	 */
	function __construct()
    {
        $message = "Please Ensure a config file is present at app/config/auth.php";

        parent::__construct($message);
    }
}
