<?php
/**
 * Loader class for managing hooks and dependencies
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Loader class
 * 
 * Maintains a list of all hooks registered throughout the plugin,
 * and registers them with WordPress when the plugin is initialized.
 */
class QAF_Loader {

    /**
     * Array of actions registered with WordPress
     *
     * @var array
     */
    protected $actions;

    /**
     * Array of filters registered with WordPress
     *
     * @var array
     */
    protected $filters;

    /**
     * Array of shortcodes registered with WordPress
     *
     * @var array
     */
    protected $shortcodes;

    /**
     * Initialize the collections
     */
    public function __construct() {
        $this->actions    = array();
        $this->filters    = array();
        $this->shortcodes = array();
    }

    /**
     * Add a new action to the collection
     *
     * @param string $hook          The WordPress hook name
     * @param object $component     Reference to the instance of the object
     * @param string $callback      The callback method name
     * @param int    $priority      Action priority
     * @param int    $accepted_args Number of arguments accepted
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Add a new filter to the collection
     *
     * @param string $hook          The WordPress hook name
     * @param object $component     Reference to the instance of the object
     * @param string $callback      The callback method name
     * @param int    $priority      Filter priority
     * @param int    $accepted_args Number of arguments accepted
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Add a new shortcode to the collection
     *
     * @param string $tag       The shortcode tag
     * @param object $component Reference to the instance of the object
     * @param string $callback  The callback method name
     */
    public function add_shortcode( $tag, $component, $callback ) {
        $this->shortcodes = $this->add( $this->shortcodes, $tag, $component, $callback, 0, 0 );
    }

    /**
     * Utility function to register hooks/shortcodes
     *
     * @param array  $hooks         Collection of hooks
     * @param string $hook          Hook name
     * @param object $component     Component instance
     * @param string $callback      Callback method
     * @param int    $priority      Priority
     * @param int    $accepted_args Accepted arguments
     * @return array
     */
    private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );

        return $hooks;
    }

    /**
     * Register all hooks with WordPress
     */
    public function run() {
        // Register filters
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register actions
        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register shortcodes
        foreach ( $this->shortcodes as $shortcode ) {
            add_shortcode(
                $shortcode['hook'],
                array( $shortcode['component'], $shortcode['callback'] )
            );
        }
    }

    /**
     * Remove an action from the collection
     *
     * @param string $hook     Hook name
     * @param string $callback Callback method
     * @return bool
     */
    public function remove_action( $hook, $callback ) {
        return $this->remove( $this->actions, $hook, $callback );
    }

    /**
     * Remove a filter from the collection
     *
     * @param string $hook     Hook name
     * @param string $callback Callback method
     * @return bool
     */
    public function remove_filter( $hook, $callback ) {
        return $this->remove( $this->filters, $hook, $callback );
    }

    /**
     * Utility function to remove hooks
     *
     * @param array  $hooks    Collection of hooks
     * @param string $hook     Hook name
     * @param string $callback Callback method
     * @return bool
     */
    private function remove( &$hooks, $hook, $callback ) {
        foreach ( $hooks as $key => $registered_hook ) {
            if ( $registered_hook['hook'] === $hook && $registered_hook['callback'] === $callback ) {
                unset( $hooks[ $key ] );
                return true;
            }
        }
        return false;
    }
}
