<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Factories
 */

namespace RymeraWebCo\WWOF\Factories;

use WP_Query;

/**
 * For querying order forms.
 *
 * @since 3.0
 */
class Order_Forms_Query extends WP_Query {

    /**
     * The context for the query.
     *
     * @since 3.0
     * @var string
     */
    protected $context;

    /**
     * Order forms query constructor.
     *
     * @param string|array $query   Should be the same with WP_Query.
     * @param string       $context The context for the query.
     */
    public function __construct( $query = '', $context = 'view' ) {

        $query = wp_parse_args( $query );

        $query['post_type'] = Order_Form::POST_TYPE;

        $this->context = $context;

        parent::__construct( $query );
    }

    /**
     * Customize the posts results.
     */
    public function get_posts() {

        parent::get_posts();

        if ( ! in_array( $this->get( 'fields' ), array( 'ids', 'id=>parent' ), true ) ) {
            $this->posts = array_map(
                function ( $post ) {

                    return Order_Form::get_instance( $post, $this->context );
                },
                $this->posts
            );
        }

        return $this->posts;
    }

    /**
     * Index the posts by ID.
     *
     * @return array|false
     */
    public function get_id_indexed_posts() {

        $posts = $this->get_posts();

        return array_combine(
            array_map(
                function ( $post ) {

                    return $post->ID;
                },
                $posts
            ),
            $posts
        );
    }
}
