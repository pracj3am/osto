Osto 
====

Smart, Simple and Tiny ORM for PHP6




Documentation
=============


1. Entity-relationship model
----------------------------

Entity is represented by any class which extends abstract class `Entity`.
Its properties are defined using the phpdoc comments like this:

    /**
     * @property sting $first_name
     * @property string $last_name
     * @property float $weight
     * @property Photos $Photos, has_many
     * @property Profile $Profile, has_one
     * @property Class $Class, belongs_to
     */
    class User extends Entity  { }

Table name, prefix, primary and foreign keys are determined automatically,
but can be also specified:

    /**
     * @table users
     * @prefix u
     * @property integer $id, column=u_id, primary_key
     * @property sting $first_name
     * @property string $last_name
     * @property float $weight
     * @property Photos $Photos, has_many, column=u_id
     * @property Profile $Profile, has_one, column=u_id
     * @property Class $Class, belongs_to, column=c_id
     */
    class User extends Entity  { }


Now you can access all properties

    $user = new User;
    $user->first_name = 'Adam';
    $user->last_name = 'Smith';
    $user->Class = $someClass;

and save entity to database

    $user->save();


You can define your own getters and setters within an entity class and also
overload any properties.

    class User extends Entity {
        public function getFullName() {
            return $this->first_name . ' ' . $this->last_name;
        }

        public function getWeight() {
            return $this->_weight*KG_TO_POUND_RATIO; //weight in kgs
        }

        public function setName($name) {
            $this->_name = trim($name);
        }
    }

To show all properties values (incl. relations) for example for debug reasons
is very simple:

    echo $user;


2. Database Abstraction Layer
-----------------------------

Database Abstraction Layer is build above dibi (<http://dibiphp.com>).
General approach to retrieve entities from database is to
instantiate Table class:

    $users = new Table('User');

Table extends DibiDataSource, so you can specify and fetch data anywhere in model or another layer of application.
For example:

    foreach ($users->where($users->weight->ge(20))->orderBy($users->weight)) {...}

Or simply:

    foreach ($users->where('u_weight > ', 20)->orderBy('u_weight')) {...}

The simpliest "magic" way is following:

    foreach (User('u_weight > ', 20)->orderBy('u_weight')) {...}


There are also some helpers methods:

    User::findAll('u_weight > ', 20); //return Table
    User::findOne('u_first_name = ', 'Adam'); //return first matching record as User
    $user = User::find(3);  //return User with primary key 3

Entities than take care of relations between them. So `$user->Class` lazily load data
of Class entity, `$user->Photos` loads or Photos (as Table object) etc.


3. Inheritance model
--------------------

The most powerful tool of Osto is that it accomplish inheritance of entities.
For example we can extend entity User:

    /**
     * @property string $phone
     * @property string $email
     * @property Customer $Customers, has_many
     */
    class Salesman extends User {}

... to be continued ...


 
License
=======
 
Copyright © 2010 Jan Prachař; Published under The GPL License

Acknowledgment
--------------

This software are using fragments of Nette Framework (caching, annotations & test framework).
Copyright (c) 2004, 2010 David Grudl (<http://nette.org>)
Nette License    <http://nettephp.com/license>

This software are using dibi (tiny'n'smart database abstraction layer).
Copyright (c) 2005, 2010 David Grudl (<http://davidgrudl.com>)