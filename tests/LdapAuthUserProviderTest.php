<?php

use Ccovey\LdapAuth;
use \Illuminate\Auth\GenericUser;
use Mockery as m;

/**
* User Provider Test
*/
class LdapAuthUserProviderTest extends PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		$this->ad = m::mock('adLDAP\adLDAP');
		$this->ad->shouldReceive('close')
			->andReturn(null);
		$this->ident = 'ccovey';
		$this->model = '\Ccovey\LdapAuth\LdapUser';

		$this->ad->shouldReceive('user')->atLeast(1)
			->andReturn($this->ad);

		$this->config = array(
			'fields' => array(),
			'userlist' => false,
			'group' => array()
		);
	}

	public function tearDown()
	{
		m::close();
	}

	public function testRetrieveByIDWithModelAndNoUserInLDAPReturnsNull()
	{
		$this->ad->shouldReceive('infoCollection')
			->once()->with($this->ident, ['*'])->andReturn(false);
		$provider = $this->getProviderMock($this->ad, 'UserStub');
		$mock = m::mock('stdClass');
		$mock->shouldReceive('newQuery')->once()->andReturnSelf();
		$mock->shouldReceive('find')->once()->with(1)->andReturn(new \Ccovey\LdapAuth\LdapUser(['username'=>$this->ident]));
		$provider->expects($this->once())->method('createModel')->will($this->returnValue($mock));

		$retrieved = $provider->retrieveByID(1);

		$this->assertNull($retrieved);
	}

	public function testRetrieveByIDWithModelAndLdapInfo()
	{
		$this->ad->shouldReceive('infoCollection')
			->once()->with($this->ident, ['*'])->andReturn($this->getLdapInfo());
		$userArray = ['username'=>$this->ident, 'foo'=>'bar'];
		$userMock = new \Ccovey\LdapAuth\LdapUser($userArray);

		$provider = $this->getProviderMock($this->ad, '\Ccovey\LdapAuth\LdapUser');
		$mock = m::mock('\Ccovey\LdapAuth\LdapUser');
		$mock->shouldReceive('newQuery')->once()->andReturn($mock);
		$mock->shouldReceive('find')->once()->with(1)->andReturn($userMock);
		$provider->expects($this->atLeastOnce())->method('createModel')->will($this->returnValue($mock));

		$user = get_object_vars($provider->retrieveById(1));

		$this->assertContains('bar', $user); //from database
		$this->assertContains('ccovey', $user); //from LDAP
	}

	public function testValidateCredentials()
	{
		$credentials = array('username' => 'ccovey', 'password' => 'password');
		$this->ad->shouldReceive('authenticate')->once()->andReturn(true);
		$user = $this->getProviderMock($this->ad, 'UserStub');
		$model = m::mock('Ccovey\LdapAuth\LdapUser');
		$validate = $user->validateCredentials($model, $credentials);

		$this->assertTrue($validate);
	}

	protected function getProviderMock($conn, $model = null)
	{
		return $this->getMock('Ccovey\LdapAuth\LdapAuthUserProvider', 
			array('createModel'), array($conn, $this->config, $model));
	}

	protected function getLdapInfo()
	{
		$info = new stdClass;

		$info->samaccountname = 'ccovey';

		$info->displayName = 'Cody Covey';

		$info->distinguishedname = 'DC=LDAP,OU=AUTH,OU=FIRST GROUP';

		$info->memberof = array();

		return $info;
	}
}
class UserStub{}