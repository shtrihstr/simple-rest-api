<?php

namespace Simple_REST_API;

use WP_REST_Request;
use WP_REST_Response;
use ReflectionFunction;

class Route {

    protected $_path;
    protected $_method;
    protected $_callback;

    protected $_custom_params = [];

    protected $_accept_json = false;

    protected $_asserts = [];
    protected $_converts = [];
    protected $_before = [];
    protected $_after = [];

    public function __construct( $method, $path, callable $callback ) {
        $this->_method = $method;

        if ( 0 !== mb_strpos( $path, '/' ) ) {
            $path = '/' . $path;
        }

        $this->_path = $path;
        $this->_callback = $callback;
    }

    public function get_method() {
        return $this->_method;
    }

    public function get_path() {
        return $this->_replace_path_custom_params( $this->_path, $this->_custom_params );
    }

    public function is_accept_json() {
        return $this->_accept_json;
    }

    public function accept_json( $accept = true )  {
        $this->_accept_json = boolval( $accept );
        return $this;
    }

    /**
     * Sets a callback to handle before triggering the route callback.
     *
     * @param callable $callback A PHP callback to be triggered when the route is matched, just before the route callback
     *
     * @return Route $this The current instance
     */
    public function before( callable $callback ) {
        $this->_before[] = $callback;
        return $this;
    }

    /**
     * Sets a callback to handle after the route callback.
     *
     * @param callable $callback A PHP callback to be triggered after the route callback
     *
     * @return Route $this The current instance
     */
    public function after( callable $callback ) {
        $this->_after[] = $callback;
        return $this;
    }

    /**
     * Sets the requirement for a route variable.
     *
     * @param string $variable The variable name
     * @param string $pattern The regexp to apply
     *
     * @return Route $this The current instance
     */
    public function assert( $variable, $pattern ) {
        $this->_asserts[ $variable ] = $pattern;
        return $this;
    }

    /**
     * Sets a converter for a route variable.
     *
     * @param string $variable The variable name
     * @param callable $callback A PHP callback that converts the original value
     *
     * @return Route $this The current instance
     */
    public function convert( $variable, callable $callback ) {
        $this->_converts[ $variable ] = $callback;
        return $this;
    }

    public function execute( WP_REST_Request $request ) {
        $response = new WP_REST_Response();

        foreach ( $this->_converts as $key => $convert_callback ) {
            if( in_array( $key, array_values( $this->_custom_params ) ) ) {
                $index = array_search( $key, $this->_custom_params );
                $url_params = $request->get_url_params();
                $url_params[ $index ] = $this->_execute_callback( $convert_callback, $request, $response );
                $request->set_url_params( $url_params );
            }
        }

        foreach ( $this->_before as $before_callback ) {
            $this->_execute_callback( $before_callback, $request, $response );
        }

        $result = $this->_execute_callback( $this->_callback, $request, $response );

        if( $result && ! $result instanceof WP_REST_Response ) {
            $response->set_data( $result );
        }

        foreach ( $this->_after as $after_callback ) {
            $this->_execute_callback( $after_callback, $request, $response );
        }

        return $response;
    }

    protected function _execute_callback( callable $callback, WP_REST_Request $request, WP_REST_Response $response ) {
        $reflection = new ReflectionFunction( $callback );
        $reflection_parameters = $reflection->getParameters();

        $args = [];
        foreach ( $reflection_parameters as $reflection_parameter ) {
            $name = $reflection_parameter->getName();

            if ( 'request' == $name ) {
                $args[ $name ] = $request;
            }
            elseif ( 'response' == $name ) {
                $args[ $name ] = $response;
            }
            elseif ( in_array( $name, array_values( $this->_custom_params ) ) ) {
                $index = array_search( $name, $this->_custom_params );
                $url_params = $request->get_url_params();
                $args[ $name ] = $url_params[ $index ];
            }
        }

        return call_user_func_array( $callback, $args );
    }

    protected function _replace_path_custom_params( $path, &$params ) {
        $params = [];
        $matches = null;
        if ( preg_match_all( '/\{([a-zA-z0-9]+)\}/i', $path, $matches ) ) {

            foreach ( $matches[1] as $param ) {
                $path_parts = explode( '{' . $param . '}', $path );

                $index = 1;
                if( count( $path_parts ) > 0 && preg_match_all( '/[^\\\\](\()/i', $path_parts[0], $groups ) ) {
                    $index = count( $groups[1] ) + 1;
                }

                $params[ $index ] = $param;
                $pattern = array_key_exists( $param, $this->_asserts ) ? $this->_asserts[ $param ] : '[^/]+';
                $path = implode( '(' . $pattern . ')', $path_parts );
            }
        }
        return $path;
    }
}