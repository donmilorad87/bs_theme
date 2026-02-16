<?php
/**
 * Auth REST Controller
 *
 * Registers all authentication REST API routes on rest_api_init.
 * Instantiates each endpoint class and calls its register() method.
 *
 * @package BSCustom\RestApi
 */

namespace BSCustom\RestApi;

use BSCustom\RestApi\Endpoints\Login;
use BSCustom\RestApi\Endpoints\Register;
use BSCustom\RestApi\Endpoints\Logout;
use BSCustom\RestApi\Endpoints\ForgotPassword;
use BSCustom\RestApi\Endpoints\VerifyActivation;
use BSCustom\RestApi\Endpoints\ResendActivation;
use BSCustom\RestApi\Endpoints\VerifyResetCode;
use BSCustom\RestApi\Endpoints\ResetPassword;
use BSCustom\RestApi\Endpoints\FormTemplate;
use BSCustom\RestApi\Endpoints\ProfileUpdate;
use BSCustom\RestApi\Endpoints\ProfileChangePassword;
use BSCustom\RestApi\Endpoints\ProfileUploadAvatar;
use BSCustom\RestApi\Endpoints\ContactSubmit;
use BSCustom\RestApi\Endpoints\ContactMessages;
use BSCustom\RestApi\Endpoints\ContactMarkRead;
use BSCustom\RestApi\Endpoints\ContactDelete;
use BSCustom\RestApi\Endpoints\ContactReply;
use BSCustom\RestApi\Endpoints\ContactUserMessages;

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
