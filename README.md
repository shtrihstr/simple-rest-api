# Simple WordPress REST API Router
WordPress REST API Router, that is really easy to use.

## Installation
#### Composer
    $ composer require shtrihstr/simple-rest-api
#### Old way
Download archive from [releases section](https://github.com/shtrihstr/simple-rest-api/releases)
```php
require_once '/path/to/Route.php';
require_once '/path/to/Router.php';
```

## Usage
#### Create a Router
```php
$router = new Simple_REST_API\Router( 'my-plugin/v1.0', [ 'etag' => true ] );
```
#### Example GET Route
Here is an example definition of a GET route:
```php
$router->get( '/posts', function() {
    return get_posts();
} );
```
#### Dynamic Routing
Now, you can create another controller for viewing individual blog posts:
```php
$router->get( '/post/{id}', function( WP_REST_Response $response, $id ) {
    $post = get_post( $id );
    if( ! $post ) {
        $response->set_status( 404 );
    }
    else {
        $response->set_data( $post );
    }
    return $response;
} );
```
#### Example POST Route
POST routes signify the creation of a resource. An example for this is a feedback form.
```php
$router->post( '/feedback', function( WP_REST_Request $request, WP_REST_Response $response ) {
    $body_params = $request->get_body_params();
    $message = esc_html( $body_params['message'] );
    
    wp_mail( 'feedback@yoursite.com', '[YourSite] Feedback', $message );

    $response->set_status( 201 );
    $response->set_data( 'Thank you for your feedback!' );
    return $response;
} );
```
#### Other methods
You can create controllers for most HTTP methods.
```php
$router->put( '/post/{id}', function( $id ) {
    // ...
} );

$router->delete( '/post/{id}', function( $id ) {
    // ...
} );

$router->patch( '/post/{id}', function( $id ) {
    // ...
} );
```
#### Route Variables
As it has been shown before you can define variable parts in a route like this:
```php
$router->get( '/post/{id}', function( $id ) {
    // ...
} );
```
It is also possible to have more than one variable part, just make sure the closure arguments match the names of the variable parts:
```php
$router->get( '/post/{post_id}/paged/{page_id}', function( $post_id, $page_id ) {
    // ...
} );
```
While it's not recommended, you could also do this (note the switched arguments):
```php
$router->get( '/post/{post_id}/paged/{page_id}', function( $page_id, $post_id ) {
    // ...
} );
```
You can also ask for the current Request and Response objects:
```php
$router->get( '/post/{id}', function( WP_REST_Request $request, WP_REST_Response $response, $id ) {
    // ...
} );
```
#### Route Variable Converters
Before injecting the route variables into the controller, you can apply some converters:
```php
$router->get( '/post/{id}', function( $id ) {
    // ...
} )->convert( 'id', function( $id ) { return (int) $id; } );
```
This is useful when you want to convert route variables to objects:
```php
$router->get( '/comments/{user}', function( $user ) {
    // ...
}) ->convert( 'user', function( $user ) { return get_user_by( 'id', $user ); } );
```
#### Requirements
The following will make sure the id argument is a positive integer, since \d+ matches any amount of digits:
```php
$router->get( '/post/{id}', function( $id ) {
    // ...
} )->assert( 'id', '\d+' );
```
#### Middlewares
Route middlewares are added to routes and they are only triggered when the corresponding route is matched. You can also stack them:
```php
$before_callback = function() {
    $GLOBALS['wpdb']->queries = [];
};

$after_callback = function( WP_REST_Response $response ) {
    $data = $response->get_data();
    if( is_array( $data ) ) {
        $data['debug'] = [
            'time' => timer_stop(),
            'queries' => $GLOBALS['wpdb']->queries,
        ];
        $response->set_data( $data );
    }
};

$router->get( '/post/{id}', function( $id ) {
    // ...
} )->before( $before_callback )->after( $after_callback );
```