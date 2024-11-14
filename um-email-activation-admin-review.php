<?php
/**
 * Plugin Name:     Ultimate Member - Email Activation and Admin Review
 * Description:     Extension to Ultimate Member for two steps of User Registration with both Email Activation and Admin Review before User is accepted.
 * Version:         1.0.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica?tab=repositories
 * Plugin URI:      https://github.com/MissVeronica/um-email-activation-admin-review
 * Update URI:      https://github.com/MissVeronica/um-email-activation-admin-review
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;

class UM_Email_Activation_Admin_Review {

    function __construct() {

        define( 'Plugin_Basename_EAAR', plugin_basename( __FILE__ ));

        add_action( 'um_after_email_confirmation', array( $this, 'um_after_email_confirmation_admin_approval' ), 10, 1 );
        add_filter( 'um_settings_structure',       array( $this, 'um_settings_email_confirmation_admin_approval' ), 10, 1 );
        add_filter( 'plugin_action_links_' . Plugin_Basename_EAAR, array( $this, 'plugin_settings_link' ), 10, 1 );
    }

    public function plugin_settings_link( $links ) {

        $url = get_admin_url() . 'admin.php?page=um_options&section=users';
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings' ) . '</a>'; 

        return $links;
    }

    public function um_after_email_confirmation_admin_approval( $user_id ) {

        um_fetch_user( $user_id );

        $registration_role = sanitize_text_field( UM()->options()->get( 'eaar_email_activation_role' ));
        $admin_review_role = sanitize_text_field( UM()->options()->get( 'eaar_admin_review_role' ));

        UM()->roles()->remove_role( $user_id, $registration_role );
        UM()->roles()->set_role( $user_id, $admin_review_role );

        UM()->user()->remove_cache( $user_id );
        um_fetch_user( $user_id );

        $user_role_data = UM()->roles()->role_data( um_user( 'role' ) );

        UM()->common()->users()->set_as_pending( $user_id, true );

        if ( isset( $user_role_data['pending_action'] )) {

            if ( $user_role_data['pending_action'] == 'redirect_url' ) {

                $redirect = $user_role_data['pending_url'];

                exit( wp_safe_redirect( $redirect ));
            }

            if ( $user_role_data['pending_action'] == 'show_message' ) {

                $forms = get_posts(array('post_type' => 'um_form', 'posts_per_page' => 1, 'meta_key' => '_um_core', 'meta_value' => 'register' ));
                $form_id = isset( $forms[0]->ID ) ? $forms[0]->ID: 0;

                $url = um_get_core_page( 'register' );
                $url = add_query_arg( 'message', esc_attr( 'pending' ), $url . '?' );
                $url = add_query_arg( 'um_role', esc_attr( um_user( 'role' ) ), $url );
                $url = add_query_arg( 'um_form_id', esc_attr( $form_id ), $url );

                exit( wp_safe_redirect( $url ));
            }
        }
    }

    public function um_settings_email_confirmation_admin_approval( $settings_structure ) {

        if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'um_options' ) {
            if ( ! isset( $_REQUEST['section'] ) || $_REQUEST['section'] == 'users' ) {

                $plugin_data = get_plugin_data( __FILE__ );

                $documention = sprintf( ' <a href="%s" target="_blank" title="%s">%s</a>',
                                                esc_url( $plugin_data['PluginURI'] ),
                                                esc_html__( 'GitHub plugin documentation and download', 'ultimate-member' ),
                                                esc_html__( 'Documentation', 'ultimate-member' ));

                $prefix = '&nbsp; * &nbsp;';

                $settings_structure['']['sections']['users']['form_sections']['email_confirmation_admin_approval'] =
                                                array(
                                                        'title'       => esc_html__( 'Email Activation and Admin Review', 'ultimate-member' ),
                                                        'description' => sprintf( esc_html__( 'Plugin version %s - tested with UM 2.9.0 - %s', 'ultimate-member' ),
                                                                                                        $plugin_data['Version'], $documention )
                                                    );

                $settings = array();

                $settings[] = array(
                                    'id'          => 'eaar_email_activation_role',
                                    'type'        => 'select',
                                    'label'       => $prefix . esc_html__( 'Email Activation User Role', 'ultimate-member' ),
                                    'description' => esc_html__( 'This will be the role assigned to Users registering through Ultimate Member registration forms and in the first step: Email Activation.', 'ultimate-member' ),
                                    'options'     => UM()->roles()->get_roles(),
                                    'size'        => 'small',
                                );

                $settings[] = array(
                                    'id'          => 'eaar_admin_review_role',
                                    'type'        => 'select',
                                    'label'       => $prefix . esc_html__( 'Admin Review User Role', 'ultimate-member' ),
                                    'description' => esc_html__( 'This will be the role assigned to Users for Admin review after Email Activation.', 'ultimate-member' ),
                                    'options'     => UM()->roles()->get_roles(),
                                    'size'        => 'small',
                                );

                $settings_structure['']['sections']['users']['form_sections']['email_confirmation_admin_approval']['fields'] = $settings;

            }
        }

        return $settings_structure;
    }
}

new UM_Email_Activation_Admin_Review();

