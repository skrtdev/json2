# JSON2

JSON2 converts json and arrays in structured classes

Full example
```php
<?php

use skrtdev\JSON2\JSONProperty;

require 'vendor/autoload.php';

function api_call(...$args): string {
    return file_get_contents('https://jsonplaceholder.typicode.com/'.implode('/', $args));
}

/** @var Post[] $posts */
$posts = json2_decode(api_call('posts'), Post::class);

class Post{

    #[JSONProperty(json: 'userId')]
    protected int $user_id;
    protected int $id;
    protected string $title;
    protected string $body;
    
    protected User $user;

    /**
     * @return User
     * @throws ReflectionException
     */
    public function getUser(): User
    {
        return $this->user ??= json2_decode(api_call('users', $this->user_id), User::class);
    }

    /**
     * @return Comment[]
     */
    public function getComments(): array
    {
        return $this->comments ??= json2_decode(api_call('posts', $this->id, 'comments'), Comment::class);
    }
}

class User{
    protected int $id;
    protected string $name;
    protected string $username;
    protected Address $address;
    protected Company $company;
}

class Address{
    protected Location $geo;
}

class Location{
    protected float $lat;
    protected float $lng;
}

class Company{
    protected string $name;
    #[JSONProperty(json: 'catchPhrase')]
    protected string $catch_phrase;
}

class Comment{
    #[JSONProperty(json: 'postId')]
    protected string $post_id;
}

var_dump($posts);

var_dump($posts[30]->getUser());
var_dump($posts[6]->getComments());
```