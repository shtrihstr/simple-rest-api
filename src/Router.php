<?php

namespace Simple_REST_API;

use WP_REST_Request;
use WP_REST_Response;

class Router {

    protected $_namespace;

    /**
     * @var Route[]
     */
    protected $_routes = [];

    /**
     * @param string $namespace The first URL segment after core prefix. Should be unique to your package/plugin.
     */
    public function __construct( $namespace ) {
        $namespace = trim( $namespace, '/' );
        $this->_namespace = $namespace;

        add_action( 'rest_api_init', function() {
            $this->_register();
        } );
    }

    /**
     * Maps a GET request to a callable.
     *
     * @param string $path Matched route path
     * @param callable $callback Callback that returns the response when matched
     * @return Route
     */
    public function get( $path, callable $callback ) {
        $route = new Route( 'GET', $path, $callback );
        $this->_routes[] = $route;
        return $route;
    }

    /**
     * Maps a POST request to a callable.
     *
     * @param string $path Matched route path
     * @param callable $callback Callback that returns the response when matched
     * @return Route
     */
    public function post( $path, callable $callback ) {
        $route = new Route( 'POST', $path, $callback );
        $this->_routes[] = $route;
        return $route;
    }

    /**
     * Maps a PUT request to a callable.
     *
     * @param string $path Matched route path
     * @param callable $callback Callback that returns the response when matched
     * @return Route
     */
    public function put( $path, callable $callback ) {
        $route = new Route( 'PUT', $path, $callback );
        $this->_routes[] = $route;
        return $route;
    }

    /**
     * Maps a PATCH request to a callable.
     *
     * @param string $path Matched route path
     * @param callable $callback Callback that returns the response when matched
     * @return Route
     */
    public function patch( $path, callable $callback ) {
        $route = new Route( 'PATCH', $path, $callback );
        $this->_routes[] = $route;
        return $route;
    }

    /**
     * Maps a DELETE request to a callable.
     *
     * @param string $path Matched route path
     * @param callable $callback Callback that returns the response when matched
     * @return Route
     */
    public function delete( $path, callable $callback ) {
        $route = new Route( 'DELETE', $path, $callback );
        $this->_routes[] = $route;
        return $route;
    }

    /**
     * Maps a request to a callable.
     *
     * @param string $method Request method
     * @param string $path Matched route path
     * @param callable $callback Callback that returns the response when matched
     * @return Route
     */
    public function match( $method, $path, callable $callback ) {
        $route = new Route( mb_strtoupper( $method ), $path, $callback );
        $this->_routes[] = $route;
        return $route;
    }

    protected function _register() {
        foreach( $this->_routes as $route ) {
            $args = [
                'methods' => $route->get_method(),
                'accept_json' => $route->is_accept_json(),
                'callback' => function( WP_REST_Request $request ) use ( $route ) {
                    $response = $route->execute( $request );
                    $this->_maybe_add_etag( $request, $response );
                    return $response;
                }
            ];
            register_rest_route( $this->_namespace, $route->get_path(), $args, true );
        }
    }

    protected function _maybe_add_etag( WP_REST_Request $request, WP_REST_Response $response ) {
        if( 'GET' === $request->get_method() && 200 == $response->get_status() ) {
            $etag = md5( serialize( $response->get_data() ) );
            $response->header( 'Etag', $etag );

            if( $etag && $etag === $request->get_header( 'if_none_match' ) ) {
                $response->set_status( 304 ); // Not Modified
                $response->set_data(null);
            }
        }
    }
}