<?php
/**
 * Plugin Name:     Ultimate Member - Email Activation and Admin Review
 * Description:     Extension to Ultimate Member for two steps of User Registration with both Email Activation and Admin Review before User is accepted.
 * Version:         2.0.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica?tab=repositories
 * Plugin URI:      https://github.com/MissVeronica/um-email-activation-admin-review
 * Update URI:      https://github.com/MissVeronica/um-email-activation-admin-review
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.10.5
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

        $admin_review_role = sanitize_text_field( UM()->options()->get( 'eaar_admin_review_role' ));

        if ( ! empty( $admin_review_role ) && $admin_review_role != um_user( 'role' ) ) {

            UM()->roles()->remove_role( $user_id, um_user( 'role' ) );
            UM()->roles()->set_role( $user_id, $admin_review_role );

            UM()->user()->remove_cache( $user_id );
            um_fetch_user( $user_id );
        }

        UM()->common()->users()->set_as_pending( $user_id, true );

        $emails = um_multi_admin_email();
        if ( ! empty( $emails ) ) {
            foreach ( $emails as $email ) {
                UM()->mail()->send( $email, 'notification_review', array( 'admin' => true ) );
            }
        }

        $user_role_data = UM()->roles()->role_data( um_user( 'role' ) );

        if ( isset( $user_role_data['status'] )) {
            $status = $user_role_data['status'];

            if ( isset( $user_role_data[$status . '_action'] )) {

                if ( $user_role_data[$status . '_action'] === 'redirect_url' ) {

                    exit( wp_safe_redirect( $user_role_data[$status . '_url'] ));
                }

                if ( $user_role_data[$status . '_action'] === 'show_message' ) {

                    $url = um_get_core_page( 'register' );
                    $url = add_query_arg( 'message', esc_attr( $status ), $url . '?' );
                    $url = add_query_arg( 'um_role', esc_attr( um_user( 'role' ) ), $url );

                    exit( wp_safe_redirect( $url ));
                }
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
                                                        'description' => sprintf( esc_html__( 'Plugin version %s - tested with UM 2.10.5 - %s', 'ultimate-member' ),
                                                                                                        $plugin_data['Version'], $documention )
                                                    );

                $settings_structure['']['sections']['users']['form_sections']['email_confirmation_admin_approval']['fields'][] = array(
                                    'id'          => 'eaar_admin_review_role',
                                    'type'        => 'select',
                                    'label'       => $prefix . esc_html__( 'Admin Review User Role', 'ultimate-member' ),
                                    'description' => esc_html__( 'This will be the role assigned to Users for Admin review after Email Activation.', 'ultimate-member' ),
                                    'options'     => UM()->roles()->get_roles(),
                                    'size'        => 'small',
                                );

            }
        }

        return $settings_structure;
    }
}

new UM_Email_Activation_Admin_Review();
