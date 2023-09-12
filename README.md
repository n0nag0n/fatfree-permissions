Fat-Free Permissions
------

This is a permissions module that can be used in your projects if you have multiple roles in your app and each role has a little bit different functionality. This module allows you to define permissions for each role and then check if the current user has the permission to access a certain page or perform a certain action. This module is designed to be used with the [Fat-Free Framework](https://fatfreeframework.com). It is not a standalone module. This module pairs very nicely with [xfra35/f3-access](https://github.com/xfra35/f3-access)

Installation
-------
Run `composer require n0nag0n/fatfree-permissions` and you're on your way!

Configuration
-------
There is very little configuration you need to do to get this started. It actually is wired up to accept configs, but it's not actually used at the moment.

Usage
-------
First you need to setup your permissions, then you tell the your app what the permissions mean. Ultimately you will check your permissions with `$Permissions->has()`, `->can()`, or `is()`. They all have the same functionality, but are named differently to make your code more readable.

### Simple Usage
```php
<?php

// bootstrap code
$f3 = Base::instance();

// some code 

// then you probably have something that tells you who the current role is of the person
// likely you have something like $f3->get('SESSION.user.role'); which defines this
// after someone logs in, otherwise they will have a 'guest' or 'public' role.
$current_role = 'admin';

// setup permissions
$Permissions = \n0nag0n\Permissions::instance($current_role);
$Permissions->defineRule('logged_in', function(Base $f3, $current_role) {
	return $current_role !== 'guest';
});

// You'll likely want to attach to this the hive
$f3->set('Permissions', $Permissions);

$f3->run();
```
Then in your template or controller you can do something like this:
```php
<?php

public function getOrder(Base $f3, array $args = []) {
	// check if the user is logged in
	if (!$f3->get('Permissions')->is('logged_in')) {
		// if not, redirect them to the login page
		$f3->reroute('/login');
	}
	// otherwise, show them the order page
	// ...
}
```

### Advanced Usage
You might have something more advanced where you have some functionality available to one role, but not another role surrounding the same thing. I'll show you what I mean. 

The permissions defined in this context are completely customizable. If you want `view`, `update`, `archive`, `soft-delete`, and `like` permissions, you can totally customize it that way. Whatever strings you append to the array can be used to check if the user has that permission.
```php
<?php

// bootstrap code
$f3 = Base::instance();

$current_role = 'manager';

// setup permissions in a CRUD like context
$Permissions = \n0nag0n\Permissions::instance($current_role);

// additionally you can inject additional dependencies into the closure/class->method
$Permissions->defineRule('order', function(Base $f3, $current_role, My_Dependency $My_Dependency = null) {
	$allowed_permissions = [ 'read' ]; // everyone can view an order
	if($current_role === 'manager' && $My_Dependency->something === 'something') {
		$allowed_permissions[] = 'create'; // managers can create orders
	}
	$some_special_toggle_from_db = $f3->get('DB')->exec('SELECT some_special_toggle FROM settings WHERE id = ?', [ $f3->get('SESSION.user_id') ])[0]['some_special_toggle'];
	if($some_special_toggle_from_db) {
		$allowed_permissions[] = 'update'; // if the user has a special toggle, they can update orders
	}
	if($current_role === 'admin') {
		$allowed_permissions[] = 'delete'; // admins can delete orders
	}
	return $allowed_permissions;
});

// You'll likely want to attach to this the hive
$f3->set('Permissions', $Permissions);

$f3->run();
```
Now the fun part comes when you want to check if a user has a certain permission regarding orders. You can do something like this:
```php
<?php

public function deleteOrder(Base $f3, array $args = []) {

	$My_Dependency = new My_Dependency('something');

	// check if the user can delete an order
	// notice where you inject the dependency
	if (!$f3->get('Permissions')->can('order.delete', $My_Dependency)) {
		// if not, redirect them to the orders page gracefully
		$f3->reroute('/orders');
	}
	// otherwise, delete the order page
	// ...
}
```

### Injecting dependencies
As you can see in the example above, you can inject dependencies into the closure that defines the permissions. This is useful if you have some sort of toggle that you want to check against. The same works for Class->Method type calls, except you define the method as such:
```php
namespace MyApp;

class Permissions {

	public function order(Base $f3, string $current_role, My_Dependency $My_Dependency = null) {
		// ... code
	}
}
```

### Shortcuts with classes
You can also use classes to define your permissions. This is useful if you have a lot of permissions and you want to keep your code clean. You can do something like this:
```php
<?php

// bootstrap code
$Permissions = \n0nag0n\Permissions::instance($current_role);
$Permissions->defineRule('order', 'MyApp\Permissions->order');

// myapp/Permissions.php
namespace MyApp;

class Permissions {

	public function order(Base $f3, string $current_role) {
		$allowed_permissions = [ 'read' ]; // everyone can view an order
		if($current_role === 'manager') {
			$allowed_permissions[] = 'create'; // managers can create orders
		}
		$some_special_toggle_from_db = $f3->get('DB')->exec('SELECT some_special_toggle FROM settings WHERE id = ?', [ $f3->get('SESSION.user_id') ])[0]['some_special_toggle'];
		if($some_special_toggle_from_db) {
			$allowed_permissions[] = 'update'; // if the user has a special toggle, they can update orders
		}
		if($current_role === 'admin') {
			$allowed_permissions[] = 'delete'; // admins can delete orders
		}
		return $allowed_permissions;
	}
}
```
The cool part is that there is also a shortcut that you can use (that is also cached!!!) where you just tell the permissions class to map all methods in a class into permissions. So if you have a method named `order()` and a method named `company()`, these will automatically be mapped so you can just run `$Permissions->has('order.read')` or `$Permissions->has('company.read')` and it will work. Defining this is very difficult, so stay with me here. You just need to do this:
```php
$Permissions = \n0nag0n\Permissions::instance($current_role);
$Permissions->defineRulesFromClassMethods(MyApp\Permissions::class, 3600); // 3600 is how many seconds to cache this for. Leave this off to not use caching
```

And away you go!

