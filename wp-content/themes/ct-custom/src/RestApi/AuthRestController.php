<?php
/**
 * Auth REST Controller
 *
 * Registers all authentication REST API routes on rest_api_init.
 * Instantiates each endpoint class and calls its register() method.
 *
 * @package CTCustom\RestApi
 */

namespace CTCustom\RestApi;

use CTCustom\RestApi\Endpoints\Login;
use CTCustom\RestApi\Endpoints\Register;
use CTCustom\RestApi\Endpoints\Logout;
use CTCustom\RestApi\Endpoints\ForgotPassword;
use CTCustom\RestApi\Endpoints\VerifyActivation;
use CTCustom\RestApi\Endpoints\ResendActivation;
use CTCustom\RestApi\Endpoints\VerifyResetCode;
use CTCustom\RestApi\Endpoints\ResetPassword;
use CTCustom\RestApi\Endpoints\FormTemplate;
use CTCustom\RestApi\Endpoints\ProfileUpdate;
use CTCustom\RestApi\Endpoints\ProfileChangePassword;
use CTCustom\RestApi\Endpoints\ProfileUploadAvatar;
use CTCustom\RestApi\Endpoints\ContactSubmit;
use CTCustom\RestApi\Endpoints\ContactMessages;
use CTCustom\RestApi\Endpoints\ContactMarkRead;
use CTCustom\RestApi\Endpoints\ContactDelete;
use CTCustom\RestApi\Endpoints\ContactReply;
use CTCustom\RestApi\Endpoints\ContactUserMessages;

class AuthRestController {

    const MAX_ENDPOINTS = 20;

    /**
     * Boot the controller: hook into rest_api_init.
     */
    public function __construct() {
        assert( function_exists( 'add_action' ), 'add_action must exist' );
        assert( class_exists( Login::class ), 'Login must be loaded' );

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register all auth endpoint routes.
     */
    public function register_routes() {
        assert( did_action( 'rest_api_init' ) > 0, 'Must be called during rest_api_init' );

        $endpoints = array(
            new Login(),
            new Register(),
            new Logout(),
            new ForgotPassword(),
            new VerifyActivation(),
            new ResendActivation(),
            new VerifyResetCode(),
            new ResetPassword(),
            new FormTemplate(),
            new ProfileUpdate(),
            new ProfileChangePassword(),
            new ProfileUploadAvatar(),
            new ContactSubmit(),
            new ContactMessages(),
            new ContactMarkRead(),
            new ContactDelete(),
            new ContactReply(),
            new ContactUserMessages(),
        );

        assert( count( $endpoints ) <= self::MAX_ENDPOINTS, 'Endpoint count must be within bounds' );

        $count = 0;
        foreach ( $endpoints as $endpoint ) {
            if ( $count >= self::MAX_ENDPOINTS ) {
                break;
            }
            $endpoint->register();
            $count++;
        }
    }
}
