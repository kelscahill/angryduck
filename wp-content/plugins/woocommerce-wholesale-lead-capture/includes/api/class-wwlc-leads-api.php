<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define the leads API.
 */
class WWLC_Leads_Api extends WWLC_Abstract_REST_Controller {
    /**
     * Property that holds the single main instance of WWPP_Dashboard.
     *
     * @since  2.0.0
     * @access private
     * @var WWLC_REST_API
     */
    private static $_instance;

    /**
     * Holds an instance of the WWLC_User_Account
     *
     * @var WWLC_User_Account
     */
    public $wwlc_user_account;

    /**
     * Holds the WWLC_Emails instance.
     *
     * @var WWLC_Emails
     */
    public $wwlc_emails;

    /**
     * Holds the WWLC_User_Custom_Fields instance
     *
     * @var WWLC_User_Custom_Fields
     */
    public $wwlc_user_custom_fields;

    /**
     * Holds the default roles.
     *
     * @var array
     */
    public $default_roles = array();

    /**
     * Holds the wholesale roles instance.
     *
     * @var WWP_Wholesale_Roles
     */
    public $wwp_wholesale_roles = null;

    /**
     * Ensure that only one instance ofthis class is created/loaded.
     *
     * @param array $dependencies The dependencies of the class.
     *
     * @since  2.0.0
     * @access public
     *
     * @return WWLC_REST_API
     */
    public static function instance( $dependencies ) {

        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * The constructor method
     *
     * Instantiates all of the routes for this api
     *
     * @param array $dependencies The dependencies of the class.
     *
     * @since 2.0.0
     */
    private function __construct( $dependencies ) {

        $this->wwlc_user_account       = $dependencies['WWLC_User_Account'];
        $this->wwlc_emails             = $dependencies['WWLC_Emails'];
        $this->wwlc_user_custom_fields = $dependencies['WWLC_User_Custom_Fields'];
        if ( class_exists( 'WWP_Wholesale_Roles' ) ) {
            $this->wwp_wholesale_roles = WWP_Wholesale_Roles::getInstance();
        }

        $this->default_roles = class_exists( 'WWLC_Helper_Functions' ) ? WWLC_Helper_Functions::get_lead_roles() : array();

        $this->collection = 'leads';
        $this->routes     = array(
            'index'              => array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'index' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
            'list'               => array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => $this->get_list_items_args(),
            ),
            'search'             => array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'search_leads' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => $this->get_list_items_args(),
            ),
            'filter'             => array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'filter_leads' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => $this->get_list_items_args(),
            ),
            'create'             => array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_lead' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            ),
            'process'            => array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'process_leads' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => array(
                    'leads'  => array(),
                    'action' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => array( $this, 'validate_action' ),
                    ),
                ),
            ),
            '(?P<user_id>[\d]+)' => array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'get_item_permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'update_item_permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                ),
            ),
        );
    }

    /**
     * The base index of the rest api
     *
     * @version 2.0.0
     * @since   2.0.0
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function index( $request ) {
        return $this->rest_response(
            array(
                'error'   => false,
                'message' => __(
                    'This is REST API for WooCommerce Wholesale Lead Capture.',
                    'woocommerce-wholesale-lead-capture'
                ),
            ),
            $request,
            200
        );
    }

    /**
     * Request query parameters.
     *
     * @version 2.0.0
     * @since   2.0.0
     *
     * @return array
     */
    public function get_list_items_args() {
        return array(
            'number'  => array(
                'sanitize_callback' => 'absint',
                'default'           => 15,
            ),
            'paged'   => array(
                'sanitize_callback' => 'absint',
                'default'           => 1,
            ),
            'offset'  => array(
                'sanitize_callback' => 'absint',
                'default'           => 0,
            ),
            'search'  => array(
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ),
            'order'   => array(
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'ASC',
            ),
            'orderby' => array(
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'login',
            ),
            'status'  => array(
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'all',
            ),
            'roles'   => array(
                'default'           => 'all',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * List leads from the database
     *
     * @version 2.0.0
     * @since   2.0.0
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_items( $request ) {
        // Get parameters.
        $args = array(
            'orderby' => $request->get_param( 'orderby' ),
            'order'   => $request->get_param( 'order' ),
            'number'  => $request->get_param( 'number' ),
            'page'    => $request->get_param( 'paged' ),
            'fields'  => 'all',
        );

        // Calculate offset properly.
        if ( $request->get_param( 'offset' ) ) {
            $args['offset'] = $request->get_param( 'offset' );
        }

        $wholesale_roles = array_keys( $this->get_wholesale_roles() );

        // Handle role/status filtering.
        $status = $request->get_param( 'status' );
        $roles  = $request->get_param( 'roles' );

        // Set up query args based on filters.
        $total_count = 0;
        if ( 'all' !== $status || 'all' !== $roles ) {
            if ( 'approved' === $status ) {
                $args['role__in'] = $wholesale_roles;
                $total_count      = $this->wwlc_user_account->count_users_by_role( $wholesale_roles );
            } elseif ( 'pending' === $status ) {
                $args['role__in'] = array( WWLC_UNAPPROVED_ROLE );
                $total_count      = $this->wwlc_user_account->count_users_by_role( WWLC_UNAPPROVED_ROLE );
            } elseif ( 'rejected' === $status ) {
                $args['role__in'] = array( WWLC_REJECTED_ROLE );
                $total_count      = $this->wwlc_user_account->count_users_by_role( WWLC_REJECTED_ROLE );
            } elseif ( $roles && 'all' !== $roles ) {
                $args['role__in'] = $this->sanitize_roles_param( $roles, $wholesale_roles, false );
                $total_count      = ! empty( $args['role__in'] ) ? $this->wwlc_user_account->count_users_by_role( $args['role__in'] ) : 0;
            }
        } else {
            $args['role__in'] = $this->default_roles;
            $total_count      = $this->wwlc_user_account->count_users_by_role( 'all' );
        }

        try {
            $users = get_users( $args );
            $leads = array_map( array( $this, 'user_lead_data' ), $users );

            return $this->rest_response(
                $this->prepare_collection_for_response(
                    array(
                        'total'      => $total_count,
                        'page'       => absint( $args['page'] ),
                        'pageSize'   => absint( $args['number'] ),
                        'leads'      => $leads,
                        'statistics' => array(
                            'all'      => $this->wwlc_user_account->count_users_by_role( 'all' ),
                            'pending'  => $this->wwlc_user_account->count_users_by_role( WWLC_UNAPPROVED_ROLE ),
                            'approved' => $this->wwlc_user_account->count_users_by_role( $wholesale_roles ),
                            'rejected' => $this->wwlc_user_account->count_users_by_role( WWLC_REJECTED_ROLE ),
                        ),
                    ),
                    'leads'
                ),
                $request,
                200
            );
        } catch ( Exception $e ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => $e->getMessage(),
                ),
                $request,
                400
            );
        }
    }

    /**
     * Search for leads by username, email, first name, last name
     *
     * @version 2.0.0
     * @since   2.0.0
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function search_leads( $request ) {
        $search = $request->get_param( 'search' );

        $args = array(
            'orderby'     => $request->get_param( 'orderby' ),
            'order'       => $request->get_param( 'order' ),
            'number'      => $request->get_param( 'number' ),
            'page'        => $request->get_param( 'paged' ),
            'fields'      => 'all',
            'count_total' => true,
            'role__in'    => $this->default_roles,
        );

        // Handle different search types.
        if ( is_email( $search ) ) {
            // Full email search.
            $args['search']         = $search;
            $args['search_columns'] = array( 'user_email' );
        } elseif ( strpos( $search, '@' ) === 0 || preg_match( '/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', $search ) ) {
            // Email domain search (e.g. @example.com or example.com).
            $domain_search          = ltrim( $search, '@' );
            $args['search']         = '*' . $domain_search . '*';
            $args['search_columns'] = array( 'user_email' );
        } else {
            // Split search term for name searches.
            $search_parts = explode( ' ', trim( $search ) );

            $meta_query = array( 'relation' => 'OR' );

            // If search has multiple terms, add name combination searches.
            if ( count( $search_parts ) > 1 ) {
                $first_name_search = sanitize_text_field( $search_parts[0] );
                $last_name_search  = sanitize_text_field( $search_parts[1] );

                $meta_query[] = array(
                    'relation' => 'AND',
                    array(
                        'relation' => 'OR',
                        array(
                            'key'     => 'first_name',
                            'value'   => $first_name_search,
                            'compare' => 'LIKE',
                        ),
                        array(
                            'key'     => 'wwlc_first_name',
                            'value'   => $first_name_search,
                            'compare' => 'LIKE',
                        ),
                    ),
                    array(
                        'relation' => 'OR',
                        array(
                            'key'     => 'last_name',
                            'value'   => $last_name_search,
                            'compare' => 'LIKE',
                        ),
                        array(
                            'key'     => 'wwlc_last_name',
                            'value'   => $last_name_search,
                            'compare' => 'LIKE',
                        ),
                    ),
                );
            } else {
                // Single term searches - use % wildcards for partial matching.
                $search = sanitize_text_field( $search );

                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'first_name',
                        'value'   => $search,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => 'last_name',
                        'value'   => $search,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => 'wwlc_username',
                        'value'   => $search,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => 'wwlc_first_name',
                        'value'   => $search,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => 'wwlc_last_name',
                        'value'   => $search,
                        'compare' => 'LIKE',
                    ),
                );
            }

            $args['meta_query'] = $meta_query;
        }

        $users = new WP_User_Query( $args );

        $total_count = $users->get_total();

        $leads = array_map( array( $this, 'user_lead_data' ), $users->get_results() );

        return $this->rest_response(
            $this->prepare_collection_for_response(
                array(
                    'total'    => $total_count,
                    'leads'    => $leads,
                    'page'     => absint( $args['page'] ),
                    'pageSize' => absint( $args['number'] ),
                ),
                'leads'
            ),
            $request,
            200
        );
    }

    /**
     * Filter leads based on status and roles.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function filter_leads( $request ) {
        $args = array(
            'orderby' => $request->get_param( 'orderby' ),
            'order'   => $request->get_param( 'order' ),
            'number'  => $request->get_param( 'number' ),
            'page'    => $request->get_param( 'paged' ),
            'fields'  => 'all',
        );

        $status = $request->get_param( 'status' );
        $roles  = $request->get_param( 'roles' );

        $wholesale_roles = array_keys( $this->get_wholesale_roles() );

        // Handle combined status and role filtering.
        if ( ! empty( $status ) && 'all' !== $status && ! empty( $roles ) && 'all' !== $roles ) {
            // Both specific status and specific roles are selected.
            // For 'approved' status, we need to filter the wholesale roles to match the requested roles.
            if ( 'approved' === $status ) {
                $requested_roles  = explode( ',', $roles );
                $filtered_roles   = array_intersect( $wholesale_roles, $requested_roles );
                $args['role__in'] = ! empty( $filtered_roles ) ? $filtered_roles : $wholesale_roles;
            } elseif ( 'pending' === $status ) {
                // For other statuses (pending/rejected), we can't combine with role filtering
                // as these are specific roles themselves.
                $args['role__in'] = array( WWLC_UNAPPROVED_ROLE );
            } elseif ( 'rejected' === $status ) {
                $args['role__in'] = array( WWLC_REJECTED_ROLE );
            } elseif ( ! empty( $roles ) && 'all' !== $roles ) {
                // Handle role-specific filter when no specific status is selected or status is 'all'.
                $args['role__in'] = $this->sanitize_roles_param( $roles, $wholesale_roles );
            } else {
                // Default case - use default roles (always wholesale-related).
                $args['role__in'] = $this->default_roles;
            }
        } elseif ( 'approved' === $status ) {
            // Handle status-specific filters.
            $args['role__in'] = $wholesale_roles;
        } elseif ( 'pending' === $status ) {
            $args['role__in'] = array( WWLC_UNAPPROVED_ROLE );
        } elseif ( 'rejected' === $status ) {
            $args['role__in'] = array( WWLC_REJECTED_ROLE );
        } elseif ( ! empty( $roles ) && 'all' !== $roles ) {
            // Handle role-specific filter when no specific status is selected or status is 'all'.
            $args['role__in'] = $this->sanitize_roles_param( $roles, $wholesale_roles );
        } else {
            // Default case - use default roles (always wholesale-related).
            $args['role__in'] = $this->default_roles;
        }

        $args = array_filter( $args );

        $users = get_users( $args );
        $leads = array_map( array( $this, 'user_lead_data' ), $users );

        // Calculate total count based on the current filters.
        $total_count = $this->wwlc_user_account->count_users_by_role( $args['role__in'] );

        return $this->rest_response(
            $this->prepare_collection_for_response(
                array(
                    'total'    => $total_count,
                    'count'    => count( $leads ),
                    'leads'    => $leads,
                    'page'     => absint( $args['page'] ),
                    'pageSize' => absint( $args['number'] ),
                ),
                'leads'
            ),
            $request,
            200
        );
    }

    /**
     * Get a single lead from the database
     *
     * @version 2.0.0
     * @since   2.0.0
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_item( $request ) {
        $user_id = (int) $request->get_param( 'user_id' );

        if ( ! $user_id ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'Invalid user id provided', 'woocommerce-wholesale-lead-capture' ),
                ),
                $request,
                400
            );
        }

        if ( ! $this->is_wholesale_lead( $user_id ) ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'User is not a wholesale lead.', 'woocommerce-wholesale-lead-capture' ),
                ),
                $request,
                403
            );
        }

        $lead = $this->get_lead( $user_id );

        if ( ! $lead ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'User not found', 'woocommerce-wholesale-lead-capture' ),
                ),
                $request,
                404
            );
        }

        return $this->rest_response(
            $this->prepare_item_for_response( $lead, $request ),
            $request,
            200
        );
    }

    /**
     * Create a single or multiple leads in the rest api
     *
     * @version 2.0.0
     * @since   2.0.0
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function create_lead( $request ) {
        $data = $request->get_json_params();
        $bulk = isset( $data['leads'] ) && is_array( $data['leads'] );

        // Handle bulk creation.
        if ( $bulk ) {
            $leads_data    = $data['leads'];
            $success_leads = array();
            $failed_leads  = array();

            foreach ( $leads_data as $user_data ) {
                // Sanitize all user data before processing.
                $user_data = wwlc_sanitize_user_data( $user_data, 'api' );

                // Process meta_data before creating the user.
                if ( isset( $user_data['meta_data'] ) && is_array( $user_data['meta_data'] ) ) {
                    $meta_data = $user_data['meta_data'];
                    unset( $user_data['meta_data'] );
                }

                // Validate required fields.
                $missing_fields  = array();
                $required_fields = array( 'user_email', 'wwlc_username' );
                foreach ( $required_fields as $field ) {
                    if ( ! isset( $user_data[ $field ] ) ) {
                        $missing_fields[] = $field;
                    }
                }

                if ( ! empty( $missing_fields ) ) {
                    $failed_leads[] = array(
                        'data'  => $user_data,
                        'error' => sprintf(
                            // translators: %s is the comma separated list of missing fields.
                            __( 'The following fields are required: %s', 'woocommerce-wholesale-lead-capture' ),
                            implode( ', ', $missing_fields )
                        ),
                    );
                    continue;
                }

                // Validate email.
                if ( ! is_email( $user_data['user_email'] ) ) {
                    $failed_leads[] = array(
                        'data'  => $user_data,
                        'error' => __( 'Invalid email address', 'woocommerce-wholesale-lead-capture' ),
                    );
                    continue;
                }

                // Create user.
                $response = $this->wwlc_user_account->wwlc_create_user(
                    $user_data,
                    $this->wwlc_emails
                );

                if ( 'success' === $response['status'] ) {
                    $user_id = $response['user_id'];

                    // Add any custom meta data after user creation.
                    // Keys are already filtered by wwlc_sanitize_user_data() blocked list.
                    if ( isset( $meta_data ) && is_array( $meta_data ) ) {
                        foreach ( $meta_data as $meta ) {
                            if ( isset( $meta['key'] ) && isset( $meta['value'] ) ) {
                                $meta_key   = sanitize_text_field( $meta['key'] );
                                $meta_value = is_array( $meta['value'] )
                                    ? array_map( 'sanitize_text_field', $meta['value'] )
                                    : sanitize_text_field( $meta['value'] );
                                update_user_meta( $user_id, $meta_key, $meta_value );
                            }
                        }
                    }

                    $success_leads[] = $this->get_lead( $user_id );
                } else {
                    $failed_leads[] = array(
                        'data'  => $user_data,
                        'error' => $response['error_message'],
                    );
                }
            }

            return $this->rest_response(
                array(
                    'object'  => 'leads',
                    'error'   => empty( $success_leads ) && ! empty( $failed_leads ),
                    'message' => ! empty( $success_leads )
                        ? __( 'Leads created successfully', 'woocommerce-wholesale-lead-capture' )
                        : __( 'Failed to create leads', 'woocommerce-wholesale-lead-capture' ),
                    'data'    => array(
                        'success'       => $success_leads,
                        'failed'        => $failed_leads,
                        'total_success' => count( $success_leads ),
                        'total_failed'  => count( $failed_leads ),
                    ),
                ),
                $request,
                ! empty( $success_leads ) ? 201 : 400
            );
        }

        // Sanitize all user data for single lead creation.
        $user_data = wwlc_sanitize_user_data( $data, 'api' );

        $required_fields = array( 'user_email', 'wwlc_username' );
        if ( ! array_intersect_key( $user_data, array_flip( $required_fields ) ) ) {
            foreach ( $required_fields as $field ) {
                if ( ! isset( $user_data[ $field ] ) ) {
                    $missing_fields[] = $field;
                }
            }

            return $this->rest_response(
                array(
                    'error'   => true,
                    'object'  => 'error',
                    'message' => sprintf(
                        // translators: %s is the comma separated list of missing fields.
                        __( 'The following fields are required: %s', 'woocommerce-wholesale-lead-capture' ),
                        implode( ', ', $missing_fields )
                    ),
                    'data'    => $missing_fields,
                ),
                $request,
                400
            );
        }

        // Validate email.
        if ( ! is_email( $user_data['user_email'] ) ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'object'  => 'error',
                    'message' => __( 'Invalid email address', 'woocommerce-wholesale-lead-capture' ),
                    'data'    => $user_data['user_email'],
                ),
                $request,
                400
            );
        }

        $response = $this->wwlc_user_account->wwlc_create_user(
            $user_data,
            $this->wwlc_emails
        );

        if ( 'success' === $response['status'] ) {
            return $this->rest_response(
                array(
                    'object'  => 'lead',
                    'error'   => false,
                    'message' => __( 'New lead created successfully', 'woocommerce-wholesale-lead-capture' ),
                    'data'    => $this->get_lead( $response['user_id'] ),
                ),
                $request,
                201
            );
        }

        return $this->rest_response(
            array(
                'error'   => true,
                'message' => sprintf(
                    // translators: %s is the error message.
                    __( 'Failed to create a lead: %s', 'woocommerce-wholesale-lead-capture' ),
                    $response['error_message']
                ),
                'data'    => $response['error_obj'] ?? null,
            ),
            $request,
            400
        );
    }

    /**
     * Update a single lead in the database
     *
     * @version 2.0.4.1
     * @since   2.0.0
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function update_item( $request ) {
        $user_id = (int) $request->get_param( 'user_id' );

        $data = $request->get_json_params();

        if ( ! $user_id ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'Invalid user id provided.', 'woocommerce-wholesale-lead-capture' ),
                ),
                $request,
                400
            );
        }

        if ( ! $this->is_wholesale_lead( $user_id ) ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'User is not a wholesale lead.', 'woocommerce-wholesale-lead-capture' ),
                ),
                $request,
                403
            );
        }

        // Fetch the stored email so we can skip the user_email field in $userdata when it
        // has not actually changed.  WordPress fires a built-in "Email Changed" notification
        // whenever user_email appears in the update array and differs from the DB value (even
        // when the difference is only cosmetic, e.g. sanitize_email() normalisation).
        $current_user  = get_userdata( $user_id );
        $current_email = $current_user ? $current_user->user_email : '';

        // Extract special fields before sanitization to prevent them from being processed incorrectly.
        // These keys are either deliberately skipped by wwlc_sanitize_user_data() or not present in
        // $WWLC_REGISTRATION_FIELDS, so they must be sanitized and handled here before the call.
        $role   = isset( $data['role'] ) ? sanitize_text_field( $data['role'] ) : '';
        $status = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : '';
        // wwlc_email is the API alias for user_email; it is not in the registration field map.
        $email = isset( $data['wwlc_email'] ) ? sanitize_email( wp_unslash( $data['wwlc_email'] ) ) : null;
        // user_url is a core WP user field, not a registration form field — also absent from the field map.
        $user_url = array_key_exists( 'user_url', $data ) ? esc_url_raw( wp_unslash( $data['user_url'] ) ) : null;

        // Sanitize the shipping address fields individually since wwlc_sanitize_user_data() skips
        // the entire 'shipping_address' key to avoid treating it as a registration form field.
        $raw_shipping = isset( $data['shipping_address'] ) && is_array( $data['shipping_address'] )
            ? $data['shipping_address']
            : null;

        $shipping_address = null;
        if ( is_array( $raw_shipping ) ) {
            $shipping_address = array(
                'same_as_billing' => isset( $raw_shipping['same_as_billing'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['same_as_billing'] ) ) : 'no',
                'first_name'      => isset( $raw_shipping['first_name'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['first_name'] ) ) : '',
                'last_name'       => isset( $raw_shipping['last_name'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['last_name'] ) ) : '',
                'address_1'       => isset( $raw_shipping['address_1'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['address_1'] ) ) : '',
                'address_2'       => isset( $raw_shipping['address_2'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['address_2'] ) ) : '',
                'city'            => isset( $raw_shipping['city'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['city'] ) ) : '',
                'postcode'        => isset( $raw_shipping['postcode'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['postcode'] ) ) : '',
                'country'         => isset( $raw_shipping['country'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['country'] ) ) : '',
                'state'           => isset( $raw_shipping['state'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['state'] ) ) : '',
                'company'         => isset( $raw_shipping['company'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['company'] ) ) : '',
                'phone'           => isset( $raw_shipping['phone'] ) ? sanitize_text_field( wp_unslash( $raw_shipping['phone'] ) ) : '',
                'email'           => isset( $raw_shipping['email'] ) ? sanitize_email( wp_unslash( $raw_shipping['email'] ) ) : '',
            );
        }

        // Sanitize update data to prevent role spoofing and other security issues.
        $data = wwlc_sanitize_user_data( $data, 'api' );

        $userdata = array(
            'ID'            => $user_id,
            'user_nicename' => isset( $data['first_name'] ) && isset( $data['last_name'] )
                ? sanitize_text_field( $data['first_name'] . ' ' . $data['last_name'] )
                : '',
        );

        // Only include user_email in the update when it was explicitly provided, is valid, and
        // actually differs from the stored value.  Omitting an unchanged email prevents WordPress
        // from firing its built-in "Email Changed" notification (wp_update_user compares the
        // incoming user_email against the DB and sends a notice whenever they differ, even when
        // the difference is only due to sanitize_email() normalisation).
        if ( null !== $email && '' !== $email && $email !== $current_email ) {
            $userdata['user_email'] = $email;
        }

        // Only update user_url when explicitly provided in the payload.
        if ( null !== $user_url ) {
            $userdata['user_url'] = $user_url;
        }

        // Handle role assignment with proper validation.
        if ( ! empty( $role ) ) {
            $userdata['role'] = $this->wwlc_user_account->maybe_assign_role( $role );
        }

        // Temporarily prevent the profile_update hook from auto-triggering wwlc_approve_user().
        // The profile_update hook is designed for WP admin user-edit-screen role changes; when
        // called here it fires before the switch statement below, causing a duplicate approval
        // email. Status-based operations are handled explicitly by the switch statement.
        $suppress_profile_approval = static function () {
            return false;
        };
        add_filter( 'wwlc_allow_profile_update_approval', $suppress_profile_approval );

        $user = wp_update_user( $userdata );

        remove_filter( 'wwlc_allow_profile_update_approval', $suppress_profile_approval );

        if ( is_wp_error( $user ) ) {
            wc_get_logger()->error(
                sprintf( 'WWLC user update failed: %s', $user->get_error_message() ),
                array( 'source' => 'woocommerce-wholesale-lead-capture' )
            );

            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'Failed to update user. Please try again or contact support.', 'woocommerce-wholesale-lead-capture' ),
                    'data'    => null,
                ),
                $request,
                400
            );
        }

        // Save registration form fields.
        $this->wwlc_user_account->save_registration_form_fields( $user_id, $data, false );
        // Save customer billing address (reads from user meta saved above).
        $this->wwlc_user_account->wwlc_save_customer_billing_address( $user_id );
        // Save customer shipping address only when the payload included shipping data.
        if ( is_array( $shipping_address ) ) {
            $this->wwlc_user_account->wwlc_save_customer_shipping_address( $user_id, $shipping_address );
        }

        // Update lead status by checking the new status and performing the necessary action.
        $valid_statuses = array( 'active', 'approved', 'pending', 'inactive', 'rejected' );
        if ( in_array( $status, $valid_statuses, true ) ) {
            switch ( $status ) {
                case 'approved':
                    $this->wwlc_user_account->wwlc_approve_user(
                        array(
                            'userID' => $user_id,
                            'role'   => $role,
                        )
                    );
                    break;
                case 'active':
                    $this->wwlc_user_account->wwlc_activate_user( array( 'userID' => $user_id ) );
                    break;
                case 'inactive':
                    $this->wwlc_user_account->wwlc_deactivate_user( array( 'userID' => $user_id ) );
                    break;
                case 'rejected':
                    $this->wwlc_user_account->wwlc_reject_user( array( 'userID' => $user_id ) );
                    break;
                case 'pending':
                default:
                    break;
            }
        }

        return $this->rest_response(
            array(
                'error'   => false,
                'message' => __( 'User details updated successfully.', 'woocommerce-wholesale-lead-capture' ),
                'data'    => $this->get_lead( $user_id ),
            ),
            $request,
            200
        );
    }

    /**
     * Delete a lead in the database
     *
     * @version 2.0.4.1
     * @since   2.0.0
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function delete_item( $request ) {
        $user_id = $request->get_param( 'user_id' );

        if ( ! $user_id || ! is_numeric( $user_id ) ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'Invalid user id provided.', 'woocommerce-wholesale-lead-capture' ),
                ),
                $request,
                400
            );
        }

        if ( ! $this->is_wholesale_lead( $user_id ) ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'User is not a wholesale lead.', 'woocommerce-wholesale-lead-capture' ),
                ),
                $request,
                403
            );
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'Cannot delete non-existent lead.', 'woocommerce-wholesale-lead-capture' ),
                ),
                $request,
                404
            );
        }

        // wp_delete_user() is defined in wp-admin/includes/user.php, which is only
        // loaded automatically in admin contexts. The REST API runs outside the admin
        // context, so we must require the file explicitly when needed.
        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $deleted = wp_delete_user( $user_id );

        if ( ! $deleted ) {
            return $this->rest_response(
                array(
                    'error'   => true,
                    'message' => __( 'Failed to delete user.', 'woocommerce-wholesale-lead-capture' ),
                    'data'    => null,
                ),
                $request,
                500,
            );
        }

        return $this->rest_response(
            array(
                'error'   => false,
                'message' => __(
                    'The lead was deleted successfully.',
                    'woocommerce-wholesale-lead-capture'
                ),
            ),
            $request,
            200,
        );
    }

    /**
     * ***********************************************************************************
     * Bulk Actions
     * ***********************************************************************************
     */
    /**
     * Check if  the provided action name is one of the expected actions.
     *
     * @param mixed $param The value of the parameter to validate.
     * @return bool|WP_Error
     */
    public function validate_action( $param ) {
        if ( ! is_string( $param ) ) {
            return new WP_Error( 'rest_invalid_action_format', __( 'Invalid `action` format, `action` must be a string.', 'woocommerce-wholesale-lead-capture' ) );
        }
        $actions = array( 'activate', 'approve', 'deactivate', 'reject' );
        $valid   = in_array( $param, $actions, true );

        if ( $valid ) {
            return true;
        }

        return new WP_Error(
            'rest_invalid_wwlc_action',
            sprintf(
                // translators: %s is the comma separated list of valid actions.
                __( 'Invalid `action` provided. Please use one of the following actions: %s', 'woocommerce-wholesale-lead-capture' ),
                implode( ', ', $actions )
            )
        );
    }

    /**
     * Process leads
     *
     * Process one or more leads. Use to approve/activate/reject/deactivate one or more leads.
     *
     * @version 2.0.0
     * @since   2.0.0
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function process_leads( $request ) {
        $lead_ids = $request->get_param( 'leads' );
        $action   = $request->get_param( 'action' );

        $leads = array_map( 'absint', wp_parse_args( $lead_ids ) );

        $processed_users   = array();
        $failed_processing = array();

        foreach ( $leads as $user_id ) {
            // Verify user exists and is a wholesale lead before processing.
            if ( ! $user_id || ! $this->is_wholesale_lead( $user_id ) ) {
                $failed_processing[] = $user_id;
                continue;
            }

            $user_data = array( 'userID' => $user_id );
            $processed = false;

            switch ( $action ) {
                case 'approve':
                    $processed = $this->wwlc_user_account->wwlc_approve_user( $user_data );
                    break;
                case 'activate':
                    $processed = $this->wwlc_user_account->wwlc_activate_user( $user_data );
                    break;
                case 'deactivate':
                    $processed = $this->wwlc_user_account->wwlc_deactivate_user( $user_data );
                    break;
                case 'reject':
                    $processed = $this->wwlc_user_account->wwlc_reject_user( $user_data );
                    break;
            }

            if ( $processed ) {
                $processed_users[ $user_id ] = $this->get_lead( $user_id );
            } else {
                $failed_processing[] = $user_id;
            }
        }

        if ( count( $processed_users ) > 0 ) {
            return $this->rest_response(
                array(
                    'error'   => false,
                    'message' => __( 'The lead was processed successfully.', 'woocommerce-wholesale-lead-capture' ),
                    'data'    => array(
                        'processed' => $processed_users,
                        'failed'    => $failed_processing,
                    ),
                ),
                $request,
                200
            );
        }

        return $this->rest_response(
            array(
                'error'   => true,
                'message' => __( 'Failed to process the lead.', 'woocommerce-wholesale-lead-capture' ),
                'data'    => array(
                    'processed' => $processed_users,
                    'failed'    => $failed_processing,
                ),
            ),
            $request,
            200
        );
    }

    /**
     * Reject leads
     *
     * Reject one or more leads.
     *
     * @version 2.0.0
     * @since   2.0.0
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function reject_leads( $request ) {
        return $this->rest_response(
            array(
                'error'   => true,
                'message' => __(
                    'There was an error rejecting the selected leads.',
                    'woocommerce-wholesale-lead-capture'
                ),
            ),
            $request,
            200,
        );
    }

    /**
     * ***********************************************************************************
     * Validators.
     * ***********************************************************************************
     */

    /**
     * ***********************************************************************************
     * Helpers.
     * ***********************************************************************************
     */

    /**
     * Check if a user is a wholesale lead (has a WWLC-managed role).
     *
     * Prevents IDOR by ensuring REST API endpoints only operate on users
     * with wholesale lead roles (unapproved, rejected, or any registered wholesale role).
     *
     * @param int $user_id The user ID to check.
     * @return bool True if the user has a wholesale lead role.
     * @since 2.0.5
     */
    private function is_wholesale_lead( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return false;
        }

        $allowed_roles = array_unique(
            array_merge(
                $this->default_roles,
                array_keys( $this->get_wholesale_roles() )
            )
        );

        return ! empty( array_intersect( (array) $user->roles, $allowed_roles ) );
    }

    /**
     * Sanitize a user-supplied roles parameter against the allowed wholesale lead roles.
     *
     * Returns only roles that are part of the WWLC role set (default lead roles +
     * registered wholesale roles). Falls back to default_roles when the intersection
     * is empty.
     *
     * @param string $roles           Comma-separated roles string from the request.
     * @param array  $wholesale_roles Registered wholesale role slugs.
     * @param bool   $fallback        Whether to fall back to default_roles when the
     *                                 intersection is empty. Default true.
     * @return array Sanitized array of role slugs (may be empty when $fallback is false).
     * @since 2.0.5
     */
    private function sanitize_roles_param( $roles, $wholesale_roles, $fallback = true ) {
        $allowed_roles = array_merge( $this->default_roles, $wholesale_roles );
        $filtered      = array_intersect( explode( ',', $roles ), $allowed_roles );

        if ( ! empty( $filtered ) ) {
            return $filtered;
        }

        return $fallback ? $this->default_roles : array();
    }

    /**
     * Get lead details.
     *
     * @param int|WP_User $user_id The user id.
     * @return array|null
     */
    public function get_lead( $user_id ) {
        $user_id = $user_id instanceof WP_User ? $user_id->ID : $user_id;
        $user    = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return null;
        }

        $lead         = $this->user_lead_data( $user );
        $addresses    = $this->get_customer_details( $user_id );
        $lead_address = array(
            'address_1' => $lead['wwlc_address'],
            'address_2' => $lead['wwlc_address_2'],
            'city'      => $lead['wwlc_city'],
            'state'     => $lead['wwlc_state'],
            'postcode'  => $lead['wwlc_postcode'],
            'country'   => $lead['wwlc_country'],
            'phone'     => $lead['wwlc_phone'],
            'email'     => $lead['wwlc_email'],
            'company'   => $lead['wwlc_company_name'],
        );

        $lead['shipping_address'] = wp_parse_args( $lead_address, $addresses['shipping_address'] );

        return $lead;
    }

    /**
     * Extract lead details from user object.
     *
     * @version 2.0.4.1
     * @since   2.0.0
     *
     * @param WP_User $user The user object.
     * @return array
     */
    public function user_lead_data( $user ) {

        $all_form_fields = array_merge( $this->wwlc_user_custom_fields->get_registration_form_fields(), $this->wwlc_user_custom_fields->get_custom_fields() );

        $username = get_user_meta( $user->ID, 'wwlc_username', true );
        $username = '' !== $username ? $username : $user->data->user_email;

        $approval_date   = get_user_meta( $user->ID, 'wwlc_approval_date', true );
        $rejection_date  = get_user_meta( $user->ID, 'wwlc_rejection_date', true );
        $is_active_meta  = get_user_meta( $user->ID, 'wwlc_is_user_active', true );
        $is_active       = wc_string_to_bool( $is_active_meta ); // make sure to check if the user is active by the meta key.
        $wholesale_roles = $this->get_wholesale_roles();

        $status             = 'inactive';
        $rejected           = $rejection_date && '' !== $rejection_date;
        $approved           = $approval_date && '' !== $approval_date;
        $pending            = WWLC_UNAPPROVED_ROLE === $user->roles[0];
        $has_wholesale_role = in_array( $user->roles[0], array_keys( $wholesale_roles ), true );

        // A lead is only considered explicitly deactivated if they have a prior approval
        // date. New unapproved leads also have wwlc_is_user_active = 'no' at registration;
        // without this guard they would incorrectly display as "Inactive" instead of "Pending".
        $is_deactivated = 'no' === $is_active_meta && $approved;

        // Explicit deactivation takes priority over other statuses.
        if ( $is_deactivated ) {
            $status = 'inactive';
        } elseif ( $rejected ) {
            $status = 'rejected';
        } elseif ( $approved || $has_wholesale_role || $is_active ) { // If user is approved, has wholesale role, or is active, then set to approved.
            $status = 'approved';
        } elseif ( $pending ) {
            $status = 'pending';
        }

        $user_data = array(
            'ID'             => $user->ID,
            'role'           => $user->roles[0],
            'name'           => $user->first_name . ' ' . $user->last_name,
            'wwlc_email'     => $user->data->user_email,
            'wwlc_username'  => $username,
            'registered'     => $user->data->user_registered,
            'approval_date'  => $approval_date,
            'rejection_date' => $rejection_date,
            'status'         => $status,
            'is_active'      => $is_active,
            'user_url'       => $user->user_url,
        );

        foreach ( $all_form_fields as $field ) {
            $user_data[ $field['name'] ] = get_user_meta( $user->ID, $field['name'], true );
        }

        return $user_data;
    }

    /**
     * Get registered wholesale roles.
     *
     * @return array
     * @since 2.0.1
     */
    public function get_wholesale_roles() {
        if ( class_exists( 'WWP_Wholesale_Roles' ) ) {
            return $this->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
        }

        return maybe_unserialize( get_option( 'wwp_options_registered_custom_roles' ) );
    }

    /**
     * Get customer details.
     *
     * Gets the customer's billing and shipping information
     *
     * @param WP_User|int $user The user id or object.
     * @return array
     */
    public function get_customer_details( $user ) {
        $user_id = $user instanceof WP_User ? $user->ID : $user;

        $same_as_billing = get_user_meta( $user_id, 'wwlc_shipping_same_as_billing', true );

        $shipping_address = array();
        if ( ! wc_string_to_bool( $same_as_billing ) ) {
            $shipping_address = array(
                'same_as_billing' => $same_as_billing,
                'first_name'      => get_user_meta( $user_id, 'shipping_first_name', true ),
                'last_name'       => get_user_meta( $user_id, 'shipping_last_name', true ),
                'company'         => get_user_meta( $user_id, 'shipping_company', true ),
                'address_1'       => get_user_meta( $user_id, 'shipping_address_1', true ),
                'address_2'       => get_user_meta( $user_id, 'shipping_address_2', true ),
                'city'            => get_user_meta( $user_id, 'shipping_city', true ),
                'postcode'        => get_user_meta( $user_id, 'shipping_postcode', true ),
                'country'         => get_user_meta( $user_id, 'shipping_country', true ),
                'state'           => get_user_meta( $user_id, 'shipping_state', true ),
                'phone'           => get_user_meta( $user_id, 'shipping_phone', true ),
                'email'           => get_user_meta( $user_id, 'shipping_email', true ),
            );
        }

        return array(
            'shipping_address' => $shipping_address,
        );
    }
}
