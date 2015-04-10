Active Directory LDAP Authentication
====================================

Laravel 5 Active Directory LDAP Authentication driver forked from ccovey/ldap-auth.
The goal is to provide a more robust LDAP Auth Driver for Laravel 5.

Implemented Features
--------------------
* Simple Config file that can be published via artisan
* Ability to map specific values to the Model from LDAP
* Ability to map specific persistent values to the model from LDAP to be stored in the database
* Session level persistence for User information retrieved from LDAP (for performance)

Future (near future) features will include:
-------------------------------------------
* Full coverage PHPUnit tests
* Ability to assign roles in database and merge with LDAP roles
* Every possible Laravel Authentication method implemented. Some are not possible, but others may be (like updating a password)
* Ability (configurable) to deny access to the application even with valid LDAP credentials when the user is not in the application database
* And finally, maybe we could be able to remove some of the setup steps and automate them.

Installation
------------
To install this [Active Directory LDAP Authentication](https://github.com/ccovey/ldap-auth) fork in your application, add the following to your `composer.json` file

```json
{
  ...
  "require": {
    "laravel/framework": "5.*",
    ...
    "ccovey/ldap-auth": "3.*",
  },
  ...
  "repositories": [{
    "type": "vcs",
    "url": "https://github.com/KerchumA222/ldap-auth"
  }],
  ...
}
```

Then run `composer update`.
NOTE: You may have to run composer with the `--prefer-source` flag in order to install this from GitHub. You will also need git installed on your machine. This is temporary and will be resolved when this is hosted on packagist.

Once you have finished downloading the package from GitHub you need to tell your Application to use the LDAP service provider.

Open `app/config/app.php` and find

`Illuminate\Auth\AuthServiceProvider`

and replace it with

`Ccovey\LdapAuth\LdapAuthServiceProvider`

This tells Laravel 5 to use the service provider from the vendor folder.

You also need to direct Auth to use the ldap driver instead of Eloquent or Database, edit `config/auth.php` and change driver to `ldap`

Configuration
-------------
To specify the username field to be used in `app/config/auth.php` set a key / value pair `'username_field' => 'fieldname'` This will default to `username` if you don't provide one.

To set up your adLDAP for connections to your domain controller run `php artisan vendor:publish` in your terminal or command line from your project directory. Then you can change your configuration options in `app\config\adldap.php`

Usage
-----
Use of `Auth` is the same as with the default service provider.

By default LDAP will grab `username (samaccountname)`, `displayname`, `primary group`, as well as all groups user is a part of.

To change what LDAP grabs you can modify `config/auth.php` and add an array called `fields` with your desired attributes.
NOTE: These attributes do not automatically get put on the model directly, but will be accessible by $user->ldap_attributes. There are instructions below to properly assign model bindings.

For more information on what fields from AD are available to you visit http://goo.gl/6jL4V

You may also get a complete user list for a specific OU by defining the `userList` key and setting it to `true`. You must also set the `group` key that defined which OU to look at. Do not that on a large AD this may slow down the application.

Model Usage
-----------
You must use a model with this implementation. 
ldap-auth can take your fields from ldap and attach them to the model allowing you to access things such as roles / permissions from the model.
You can also specify certain fields to persist to your database from LDAP:
```php
use Ccovey\LdapAuth\LdapUser;
class User extends LdapUser {
	...
	public $groups; //having this prevents Laravel from saving the '$groups' attribute to the database
	public $ldap_persistent = [
		// model_attribute_name=>ldap_attribute_name
		'name' => 'displayname', //$user->name gets re-saved to the database upon login
		'lastname' => 'sn',
		'groups' //You can also use this convention when you are mapping to an attribute of the same name
	];
	protected $appends = ['groups']; //tells Laravel to output this attribute when being converted to arrays or JSON
	public function getGroupsAttribute(){ //goes with above
		return $this->groups;
	}
    	...
```
This is useful for when you want to query your list of users for some attribute. (EX. LDAP attribute `sn`, the user's surname, could be persisted and searched on with `User::where('lastname', '=', 'Kerchum')->first()`)
It is also important to note that no authentication takes place off of the model. 
All authentication is done from Active Directory and if the user is removed from AD but still in a users table they WILL NOT be able to log in.