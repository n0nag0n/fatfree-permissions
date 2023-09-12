<?php
declare(strict_types=1);

namespace n0nag0n;

class Permissions extends \Prefab {

	/** @var array */
	protected $rules = [];

	/** @var \Base */
	protected $f3;

	/** @var string */
	protected $current_role;

	/**
	 * Constructor
	 * @param array $config
	 * @param Base $f3
	 */
	public function __construct(string $current_role = '', array $config = null, \Base $f3 = null) {
		$this->f3 = $f3 === null ? \Base::instance() : $f3;
		$this->current_role = $current_role;
		if($config !== null) {
			$config = (array) $this->f3->get('PERMISSIONS');
		}
	}

	/**
	 * Sets the current user role
	 *
	 * @param string $current_role the current role of the logged in user
	 * @return void
	 */
	public function setCurrentRole(string $current_role) {
		$this->current_role = $current_role;
	}

	/**
	 * Defines a new rule for a permission
	 *
	 * @param string 		  $rule     the generic name of the rule
	 * @param callable|string $callable either a callable, or the name of the Class->method to call that will return the allowed permissions. 
	 * 		This will return an array of strings, each string being a permission
	 *      e.g. return true; would allow access to this permission
	 * 			 return [ 'create', 'read', 'update', 'delete' ] would allow all permissions
	 * 			 return [ 'read' ] would only allow the read permission. 
	 * 			 You define what the callable or method returns for allowed permissions
	 *      The callable or method will be passed the following parameters:
	 * 			- $f3: the \Base instance
	 * 			- $current_role: the current role of the logged in user
	 * @param bool 			  $overwrite if true, will overwrite any existing rule with the same name
	 * @return void
	 */
	public function defineRule(string $rule, $callable_or_method, bool $overwrite = false) {
		if($overwrite === false && isset($this->rules[$rule]) === true) {
			throw new \Exception('Rule already defined: '.$rule);
		}
		$this->rules[$rule] = $callable_or_method;
	}

	/**
	 * Defines rules based on the public methods of a class
	 *
	 * @param string $class_name the name of the class to define rules from
	 * @return void
	 */
	public function defineRulesFromClassMethods(string $class_name, int $ttl = 0): void {

		$use_cache = false;
		if($this->f3->CACHE && $ttl > 0) {
			$use_cache = true;
			$Cache = \Cache::instance();
			$cache_key = 'permissions_class_methods_'.$class_name;
			$does_exist = $Cache->exists($cache_key, $rules);
			if($does_exist && $does_exist[0] + $ttl > microtime(true)) {
				$this->rules = array_merge($this->rules, $rules);
				return;
			}
		}

		$reflection = new \ReflectionClass($class_name);
		$methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
		$class_rules = [];
		foreach($methods as $method) {
			$method_name = $method->getName();
			if($method_name === '__construct' || strpos($method_name, '__') === 0) {
				continue;
			}
			$class_rules[$method_name] = $class_name.'->'.$method_name;
		}

		if($use_cache === true) {
			$Cache->set($cache_key, $class_rules, $ttl);
		}

		$this->rules = array_merge($this->rules, $class_rules);
	}

	/**
	 * Checks if the current user has permission to perform the action
	 *
	 * @param string $permission the permission to check. This can be the rule you defined, or a permission.action
	 * 		e.g. 'video.create' or 'video' depending on how you setup the callback.
	 * @param mixed $additional_args any additional arguments to pass to the callback or method.
	 * @return bool
	 */
	public function can(string $permission, ...$additional_args): bool {
		$allowed = false;
		$action = '';
		if(strpos($permission, '.') !== false) {
			$kaboom = explode('.', $permission);
			$permission = $kaboom[0];
			$action = $kaboom[1];
		}

		$permissions_raw = $this->rules[$permission] ?? null;
		if($permissions_raw === null) {
			throw new \Exception('Permission not defined: '.$permission);
		}
		$executed_permissions = null;
		if(is_callable($permissions_raw) === true) {
			$executed_permissions = $permissions_raw($this->f3, $this->current_role, ...$additional_args);
		} else if(is_string($permissions_raw) === true) {
			$executed_permissions = $this->f3->call($permissions_raw, [ $this->f3, $this->current_role, ...$additional_args ]);
		}

		if(is_array($executed_permissions) === true) {
			$allowed = in_array($action, $executed_permissions, true) === true;
		} else if(is_bool($executed_permissions) === true) {
			$allowed = $executed_permissions;
		}

		return $allowed;
	}

	/**
	 * Alias for can. Sometimes it's nice to say has instead of can
	 *
	 * @param string $permission [description]
	 * @return boolean
	 */
	public function has(string $permission): bool {
		return $this->can($permission);
	}

	/**
	 * Checks if the current user has the given role
	 *
	 * @param string $role [description]
	 * @return boolean
	 */
	public function is(string $role): bool {
		return $this->current_role === $role;
	}
}