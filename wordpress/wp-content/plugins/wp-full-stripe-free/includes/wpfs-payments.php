<?php

/**
 * Class MM_WPFS_Stripe
 *
 * deals with calls to Stripe API
 *
 */
class MM_WPFS_Stripe {
	use MM_WPFS_Logger_AddOn;
	use MM_WPFS_StaticContext_AddOn;

	const DESIRED_STRIPE_API_VERSION = '2020-08-27';

	/**
	 * @var string
	 */
	const INVALID_NUMBER_ERROR = 'invalid_number';
	/**
	 * @var string
	 */
	const INVALID_NUMBER_ERROR_EXP_MONTH = 'invalid_number_exp_month';
	/**
	 * @var string
	 */
	const INVALID_NUMBER_ERROR_EXP_YEAR = 'invalid_number_exp_year';
	/**
	 * @var string
	 */
	const INVALID_EXPIRY_MONTH_ERROR = 'invalid_expiry_month';
	/**
	 * @var string
	 */
	const INVALID_EXPIRY_YEAR_ERROR = 'invalid_expiry_year';
	/**
	 * @var string
	 */
	const INVALID_CVC_ERROR = 'invalid_cvc';
	/**
	 * @var string
	 */
	const INCORRECT_NUMBER_ERROR = 'incorrect_number';
	/**
	 * @var string
	 */
	const EXPIRED_CARD_ERROR = 'expired_card';
	/**
	 * @var string
	 */
	const INCORRECT_CVC_ERROR = 'incorrect_cvc';
	/**
	 * @var string
	 */
	const INCORRECT_ZIP_ERROR = 'incorrect_zip';
	/**
	 * @var string
	 */
	const CARD_DECLINED_ERROR = 'card_declined';
	/**
	 * @var string
	 */
	const MISSING_ERROR = 'missing';
	/**
	 * @var string
	 */
	const PROCESSING_ERROR = 'processing_error';
	/**
	 * @var string
	 */
	const MISSING_PAYMENT_INFORMATION = 'missing_payment_information';
	/**
	 * @var string
	 */
	const COULD_NOT_FIND_PAYMENT_INFORMATION = 'Could not find payment information';

	/* @var $stripe \StripeWPFS\StripeClient */
	public $stripe;

	/* @var MM_WPFS_Options */
	private $options;

	/* @var string */
	private $apiMode;

	/* @var bool */
	private $usingWpTestPlatform;

	/* @var bool */
	private $usingWpLivePlatform;

	/* @var string */
	private $liveStripeAcountId;

	/* @var string */
	private $testStripeAcountId;

	/* @var bool */
	private $validLicense;

	/* @var string */
	private $connectMode;

	/* @var string */
	private $connectUrl;

	/* @var  string */
	private $userVersion;

	/**
	 * MM_WPFS_Stripe constructor.
	 *
	 * @param $token
	 *
	 * @throws Exception
	 */
	public function __construct( $token, $loggerService ) {
		$this->initLogger( $loggerService, MM_WPFS_LoggerService::MODULE_STRIPE );
		$this->options = new MM_WPFS_Options();

		$this->initStaticContext();
		$this->apiMode = $this->options->get( MM_WPFS_Options::OPTION_API_MODE );
		$this->usingWpTestPlatform = $this->options->get( MM_WPFS_Options::OPTION_USE_WP_TEST_PLATFORM );
		$this->usingWpLivePlatform = $this->options->get( MM_WPFS_Options::OPTION_USE_WP_LIVE_PLATFORM );
		$this->liveStripeAcountId = $this->options->get( MM_WPFS_Options::OPTION_LIVE_ACCOUNT_ID );
		$this->testStripeAcountId = $this->options->get( MM_WPFS_Options::OPTION_TEST_ACCOUNT_ID );
		$this->connectUrl = $this->options->getFunctionsUrl();
		$this->userVersion = MM_WPFS::get_user_version();

		if ( ! is_null( $token ) && ! empty( $token ) ) {
			try {
				$this->stripe = self::createStripeClient( $token );
			} catch (Exception $ex) {
				$this->logger->error( __FUNCTION__, 'Error while initializing the Stripe client', $ex );
			}
		}
		$this->validLicense = WPFS_License::is_active();
	}

	/**
	 * @param $token string
	 * @return \StripeWPFS\StripeClient
	 */
	public static function createStripeClient( $token ) {
		return new \StripeWPFS\StripeClient( [ 
			"api_key" => $token,
			"stripe_version" => self::DESIRED_STRIPE_API_VERSION
		] );
	}

	/**
	 * @param $context MM_WPFS_StaticContext
	 * @param $liveMode
	 * @return mixed
	 */
	public static function getStripeAuthenticationTokenByMode( $context, $liveMode ) {
		$optionKey = $liveMode ? MM_WPFS_Options::OPTION_API_LIVE_SECRET_KEY : MM_WPFS_Options::OPTION_API_TEST_SECRET_KEY;

		return $context->getOptions()->get( $optionKey );
	}

	/**
	 * @param $context MM_WPFS_StaticContext
	 * @return string
	 */
	public static function getStripeAuthenticationToken( $context ) {
		return MM_WPFS_Stripe::getStripeAuthenticationTokenByMode( $context, self::isStripeApiInLiveMode( $context ) );
	}

	/**
	 * @param $context MM_WPFS_StaticContext
	 * @return bool
	 */
	public static function isStripeApiInLiveMode( $context ) {
		return $context->getOptions()->get( MM_WPFS_Options::OPTION_API_MODE ) === MM_WPFS::STRIPE_API_MODE_LIVE;
	}

	/**
	 * @return mixed
	 */
	public static function getStripeTestAuthenticationToken( $context ) {
		return $context->getOptions()->get( MM_WPFS_Options::OPTION_API_TEST_SECRET_KEY );
	}

	/**
	 * @return mixed
	 */
	public static function getStripeLiveAuthenticationToken( $context ) {
		return $context->getOptions()->get( MM_WPFS_Options::OPTION_API_LIVE_SECRET_KEY );
	}

	function getErrorCodes() {
		return array(
			self::INVALID_NUMBER_ERROR,
			self::INVALID_NUMBER_ERROR_EXP_MONTH,
			self::INVALID_NUMBER_ERROR_EXP_YEAR,
			self::INVALID_EXPIRY_MONTH_ERROR,
			self::INVALID_EXPIRY_YEAR_ERROR,
			self::INVALID_CVC_ERROR,
			self::INCORRECT_NUMBER_ERROR,
			self::EXPIRED_CARD_ERROR,
			self::INCORRECT_CVC_ERROR,
			self::INCORRECT_ZIP_ERROR,
			self::CARD_DECLINED_ERROR,
			self::MISSING_ERROR,
			self::PROCESSING_ERROR,
			self::MISSING_PAYMENT_INFORMATION
		);
	}

	/**
	 * @throws WPFS_UserFriendlyException
	 */
	function remoteRequest( $method, $url, $body = null ) {
		$response = null;
		if ( $method === 'get' ) {
			$response = wp_remote_get( $this->connectUrl . $url,
				array(
					'timeout' => 10
				) );
		} else if ( $method === 'post' ) {
			$payload = null;
			// if there is a valid license, we need to send the license id and user id to the cloud function
			if ( is_array( $body ) && isset( $body["validLicense"] ) && $body["validLicense"] === true ) {
				// add the license id and user id to the body before sending it
				if ( WPFS_License::get_user_id() ) {
					$license_user_id = WPFS_License::get_user_id();
					$body["license_user_id"] = $license_user_id;
				}
				if ( WPFS_License::get_key() ) {
					$license_id = WPFS_License::get_key();
					$body["license_id"] = $license_id;
				}
			}
			if ( isset( $body ) && ! empty( $body ) ) {
				if ( isset( $body['amount'] ) ) {
					// make the amount an integer to avoid issues when encoding the payload
					$body['amount'] = intval( $body['amount'] );
				}
				$payload = json_encode( $body );
				if ( $payload == false ) {
					$this->logger->error(
						__FUNCTION__,
						'Error while encoding the payload: ' . json_last_error_msg()
					);
					throw new WPFS_UserFriendlyException( 'Error while encoding the payload: ' . json_last_error_msg() );
				}
			}
			$response = wp_remote_post(
				$this->connectUrl . $url,
				array(
					'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
					'body' => $payload,
					'timeout' => 10
				)
			);
		}

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$this->logger->error(
				__FUNCTION__,
				'API request failed: ' . ( is_array( $response ) ? $response['body'] : $response->get_error_message() )
			);
			throw new WPFS_UserFriendlyException( 'API request failed: ' . ( is_array( $response ) ? $response['body'] : $response->get_error_message() ) );
			//                echo 'API request failed: ' . (is_array($response) ? $response['body'] : $response->get_error_message());
		} else {
			$body = wp_remote_retrieve_body( $response );
			return json_decode( $body );
		}
	}

	/**
	 * @param string $stripeCustomerId
	 * @param string $stripePlanId
	 *
	 * @return \StripeWPFS\Subscription
	 * @throws Exception
	 */
	public function subscribeCustomerToPlan( $stripeCustomerId, $stripePlanId ) {
		$subscriptionData = array(
			'customer' => $stripeCustomerId,
			'items' => array(
				array(
					'price' => $stripePlanId,
				)
			)
		);

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$subscriptionData = array_merge( $subscriptionData, array( 'validLicense' => $this->validLicense ) );
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscription?mode=test&accountId=' . $this->testStripeAcountId . '&api_version=' . $this->userVersion,
				$subscriptionData
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$subscriptionData = array_merge( $subscriptionData, array( 'validLicense' => $this->validLicense ) );
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscription?mode=live&accountId=' . $this->liveStripeAcountId . '&api_version=' . $this->userVersion,
				$subscriptionData
			);
		} else {
			$stripeSubscription = json_decode( $this->stripe->subscriptions->create( $subscriptionData )->toJSON() );
		}

		return $stripeSubscription;
	}

	/**
	 * @param \StripeWPFS\Subscription $stripeSubscription
	 * @param string $quantity
	 *
	 * @throws Exception
	 */
	public function createUsageRecordForSubscription( $stripeSubscription, $quantity ) {
		$stripeSubscriptionItem = $stripeSubscription->items->data[0];

		$body = [ 
			'subscriptionItemId' => $stripeSubscriptionItem->id,
			'quantity' => $quantity,
			'timestamp' => time() + 5 * 60,
			'action' => 'set'
		];

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$this->remoteRequest(
				'post',
				'/subscription/item/usage_record?mode=test&accountId=' . $this->testStripeAcountId,
				$body
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$this->remoteRequest(
				'post',
				'/subscription/item/usage_record?mode=live&accountId=' . $this->liveStripeAcountId,
				$body
			);
		} else {
			$this->stripe->subscriptionItems->createUsageRecord(
				$stripeSubscriptionItem->id,
				[ 
					'quantity' => $quantity,
					/*
					 * We add 5 minutes to avoid the following Stripe error message:
					 * "Cannot create the usage record with this timestamp because timestamps must be after
					 *  the subscription's last invoice period (or current period start time)."
					 */
					'timestamp' => time() + 5 * 60,
					'action' => 'set',
				]
			);
		}
	}

	/**
	 * @param $stripePaymentMethodId
	 *
	 * @return \StripeWPFS\PaymentMethod
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws Exception
	 */
	public function validatePaymentMethodCVCCheck( $stripePaymentMethodId ) {
		/* @var $paymentMethod \StripeWPFS\PaymentMethod */
		$paymentMethod = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_method?mode=test&accountId=' . $this->testStripeAcountId . '&paymentMethodId=' . $stripePaymentMethodId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_method?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentMethodId=' . $stripePaymentMethodId
			);
		} else {
			$paymentMethod = json_decode( $this->stripe->paymentMethods->retrieve( $stripePaymentMethodId )->toJSON() );
		}

		if ( $paymentMethod->type === 'card' && is_null( $paymentMethod->card->checks->cvc_check ) && is_null( $paymentMethod->card->wallet ) ) {
			throw new Exception(
				/* translators: Validation error message for a card number without a CVC code */
				__( 'Please enter a CVC code', 'wp-full-stripe-free' )
			);
		}

		return $paymentMethod;
	}

	/**
	 * @param $ctx MM_WPFS_CreateSubscriptionContext
	 * @param $options MM_WPFS_CreateSubscriptionOptions
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 * @return \StripeWPFS\Subscription
	 */
	public function createSubscriptionForCustomer( $ctx, $options ) {
		$stripeCustomer = $this->retrieveCustomer( $ctx->stripeCustomerId );

		$useTestFunctions = $this->apiMode === 'test' && $this->usingWpTestPlatform;
		$useLiveFunctions = $this->apiMode === 'live' && $this->usingWpLivePlatform;

		$recurringPrice = null;
		if ( $useTestFunctions ) {
			$recurringPrice = $this->remoteRequest(
				'get',
				'/price?mode=test&accountId=' . $this->testStripeAcountId . '&priceId=' . $ctx->stripePriceId
			);
		} elseif ( $useLiveFunctions ) {
			$recurringPrice = $this->remoteRequest(
				'get',
				'/price?mode=live&accountId=' . $this->liveStripeAcountId . '&priceId=' . $ctx->stripePriceId
			);
		} else {
			$recurringPrice = json_decode( $this->stripe->prices->retrieve( $ctx->stripePriceId )->toJSON() );
		}

		if ( ! isset( $recurringPrice ) ) {
			throw new Exception( "Recurring price with id '" . $ctx->stripePriceId . " doesn't exist." );
		}

		if ( ! is_null( $ctx->stripePaymentMethodId ) ) {
			$paymentMethod = $this->retrievePaymentMethod( $ctx->stripePaymentMethodId );
			$this->attachPaymentMethodToCustomer( $paymentMethod, $stripeCustomer->id );

			$params = array(
				'invoice_settings' => array(
					'default_payment_method' => $ctx->stripePaymentMethodId
				)
			);
			$this->updateCustomerDetails( $stripeCustomer, $params );
		}

		if ( $ctx->setupFee > 0 ) {
			$setupFeeParams = array(
				'customer' => $stripeCustomer->id,
				'currency' => $recurringPrice->currency,
				'description' => sprintf(
					/* translators: It's a line item for the initial payment of a subscription */
					__( 'One-time setup fee (plan: %s)', 'wp-full-stripe-free' ),
					MM_WPFS_Localization::translateLabel( $ctx->productName )
				),
				'quantity' => $ctx->stripePlanQuantity,
				'unit_amount' => $ctx->setupFee,
				'metadata' => [ 
					'type' => 'setupFee',
					'webhookUrl' => esc_attr( MM_WPFS_EventHandler::getWebhookEndpointURL( $this->staticContext ) ),
				]
			);
			if ( ! $ctx->isStripeTax ) {
				$setupFeeParams['tax_rates'] = $options->taxRateIds;
			}

			if ( $useTestFunctions ) {
				$this->remoteRequest(
					'post',
					'/invoice/item?mode=test&accountId=' . $this->testStripeAcountId,
					$setupFeeParams
				);
			} elseif ( $useLiveFunctions ) {
				$this->remoteRequest(
					'post',
					'/invoice/item?mode=live&accountId=' . $this->liveStripeAcountId,
					$setupFeeParams
				);
			} else {
				$this->stripe->invoiceItems->create( $setupFeeParams );
			}

		}

		$hasBillingCycleAnchor = $ctx->billingCycleAnchorDay > 0;
		$hasMonthlyBillingCycleAnchor = $recurringPrice->recurring->interval === 'month' && $hasBillingCycleAnchor;
		$hasTrialPeriod = $ctx->trialPeriodDays > 0;

		$subscriptionItemsParams = array(
			array(
				'price' => $recurringPrice->id,
				'quantity' => $ctx->stripePlanQuantity,
			)
		);

		if ( ! $ctx->isStripeTax ) {
			$subscriptionItemsParams[0]['tax_rates'] = $options->taxRateIds;
		}

		if ( isset( $ctx->feeRecoveryLineItem ) && ! empty( $ctx->feeRecoveryLineItem ) ) {
			$subscriptionItemsParams[] = $ctx->feeRecoveryLineItem;
		}

		$subscriptionData = array(
			'customer' => $stripeCustomer->id,
			'items' => $subscriptionItemsParams,
			'expand' => array(
				'latest_invoice',
				'latest_invoice.payment_intent',
				'latest_invoice.charge',
				'pending_setup_intent'
			),

		);
		if ( ! empty( $ctx->discountId ) ) {
			// it's all coupon codes now
			$subscriptionData['discounts'] = array(
				array( 'coupon' => $ctx->discountId )
			);
		}
		if ( $hasTrialPeriod ) {
			$subscriptionData['trial_period_days'] = $ctx->trialPeriodDays;
		}
		if ( $hasMonthlyBillingCycleAnchor ) {
			if ( $hasTrialPeriod ) {
				$subscriptionData['billing_cycle_anchor'] = MM_WPFS_Utils::calculateBillingCycleAnchorFromTimestamp(
					$ctx->billingCycleAnchorDay,
					MM_WPFS_Utils::calculateTrialEndFromNow( $ctx->trialPeriodDays )
				);
			} else {
				$subscriptionData['billing_cycle_anchor'] = MM_WPFS_Utils::calculateBillingCycleAnchorFromNow( $ctx->billingCycleAnchorDay );
			}

			if ( $ctx->prorateUntilAnchorDay === 1 ) {
				$subscriptionData['proration_behavior'] = 'create_prorations';
			} else {
				$subscriptionData['proration_behavior'] = 'none';
			}
		}
		if ( ! is_null( $ctx->metadata ) ) {
			$subscriptionData['metadata'] = $ctx->metadata;
		}
		if ( $ctx->isStripeTax ) {
			$subscriptionData['automatic_tax'] = [ 
				'enabled' => true
			];
		}
		$subscriptionData['metadata']['webhookUrl'] = esc_attr( MM_WPFS_EventHandler::getWebhookEndpointURL( $this->staticContext ) );

		$stripeSubscription = null;
		if ( $useTestFunctions ) {
			$subscriptionData = array_merge( $subscriptionData, array( 'validLicense' => $this->validLicense ) );
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscription?mode=test&accountId=' . $this->testStripeAcountId . '&api_version=' . $this->userVersion,
				$subscriptionData
			);
		} elseif ( $useLiveFunctions ) {
			$subscriptionData = array_merge( $subscriptionData, array( 'validLicense' => $this->validLicense ) );
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscription?mode=live&accountId=' . $this->liveStripeAcountId . '&api_version=' . $this->userVersion,
				$subscriptionData
			);

		} else {
			$stripeSubscription = json_decode( $this->stripe->subscriptions->create( $subscriptionData )->toJSON() );
		}

		return $stripeSubscription;
	}

	/**
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function attachPaymentMethodToCustomer( $paymentMethod, $customerId ) {
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_method/attach?mode=test&accountId=' . $this->testStripeAcountId . '&paymentMethodId=' . $paymentMethod->id . '&customerId=' . $customerId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_method/attach?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentMethodId=' . $paymentMethod->id . '&customerId=' . $customerId
			);
		} else {
			$this->stripe->paymentMethods->attach( $paymentMethod->id, array( 'customer' => $customerId ) );
		}

		return $paymentMethod;
	}

	/**
	 * @param $customerId
	 *
	 * @return \StripeWPFS\Customer
	 * @throws WPFS_UserFriendlyException
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 */
	public function retrieveCustomer( $customerId ) {
		$customer = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$customer = $this->remoteRequest(
				'get',
				'/customer?mode=test&accountId=' . $this->testStripeAcountId . '&customerId=' . $customerId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$customer = $this->remoteRequest(
				'get',
				'/customer?mode=live&accountId=' . $this->liveStripeAcountId . '&customerId=' . $customerId
			);
		} else {
			if ( is_null( $this->stripe ) ) {
				throw new WPFS_UserFriendlyException( 'Stripe client is not initialized' );
			}
			$customer = json_decode( $this->stripe->customers->retrieve( $customerId )->toJSON() );
		}
		return $customer;
	}

	/**
	 * @param $customerId
	 * @param $params
	 *
	 * @return \StripeWPFS\Customer
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrieveCustomerWithParams( $customerId, $params ) {
		$customer = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$customer = $this->remoteRequest(
				'post',
				'/customer/search?mode=test&accountId=' . $this->testStripeAcountId . '&customerId=' . $customerId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$customer = $this->remoteRequest(
				'post',
				'/customer/search?mode=live&accountId=' . $this->liveStripeAcountId . '&customerId=' . $customerId,
				$params
			);
		} else {
			$customer = json_decode( $this->stripe->customers->retrieve( $customerId, $params )->toJSON() );
		}

		return $customer;
	}

	/**
	 * @param $productId
	 *
	 * @return \StripeWPFS\Product
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrieveProduct( $productId ) {
		$product = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$product = $this->remoteRequest(
				'get',
				'/product/?mode=test&accountId=' . $this->testStripeAcountId . '&productId=' . $productId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$product = $this->remoteRequest(
				'get',
				'/product/?mode=live&accountId=' . $this->liveStripeAcountId . '&productId=' . $productId
			);
		} else {
			$product = json_decode( $this->stripe->products->retrieve( $productId )->toJSON() );
		}
		return $product;
	}

	/**
	 * @param $code
	 *
	 * @return string|void
	 */
	function resolveErrorMessageByCode( $code ) {
		if ( $code === self::INVALID_NUMBER_ERROR ) {
			$resolved_message =  /* translators: message for Stripe error code 'invalid_number' */
				__( 'Your card number is invalid.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INVALID_EXPIRY_MONTH_ERROR || $code === self::INVALID_NUMBER_ERROR_EXP_MONTH ) {
			$resolved_message = /* translators: message for Stripe error code 'invalid_expiry_month' */
				__( 'Your card\'s expiration month is invalid.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INVALID_EXPIRY_YEAR_ERROR || $code === self::INVALID_NUMBER_ERROR_EXP_YEAR ) {
			$resolved_message = /* translators: message for Stripe error code 'invalid_expiry_year' */
				__( 'Your card\'s expiration year is invalid.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INVALID_CVC_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'invalid_cvc' */
				__( 'Your card\'s security code is invalid.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INCORRECT_NUMBER_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'incorrect_number' */
				__( 'Your card number is incorrect.', 'wp-full-stripe-free' );
		} elseif ( $code === self::EXPIRED_CARD_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'expired_card' */
				__( 'Your card has expired.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INCORRECT_CVC_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'incorrect_cvc' */
				__( 'Your card\'s security code is incorrect.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INCORRECT_ZIP_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'incorrect_zip' */
				__( 'Your card\'s zip code failed validation.', 'wp-full-stripe-free' );
		} elseif ( $code === self::CARD_DECLINED_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'card_declined' */
				__( 'Your card was declined.', 'wp-full-stripe-free' );
		} elseif ( $code === self::MISSING_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'missing' */
				__( 'There is no card on a customer that is being charged.', 'wp-full-stripe-free' );
		} elseif ( $code === self::PROCESSING_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'processing_error' */
				__( 'An error occurred while processing your card.', 'wp-full-stripe-free' );
		} elseif ( $code === self::MISSING_PAYMENT_INFORMATION ) {
			$resolved_message = /* translators: Stripe error message 'Missing payment information' */
				__( 'Missing payment information', 'wp-full-stripe-free' );
		} elseif ( $code === self::COULD_NOT_FIND_PAYMENT_INFORMATION ) {
			$resolved_message = /* translators: Stripe error message 'Could not find payment information' */
				__( 'Could not find payment information', 'wp-full-stripe-free' );
		} else {
			$resolved_message = null;
		}

		return $resolved_message;
	}

	/**
	 * @param $id
	 * @param $name
	 * @param $currency
	 * @param $interval
	 * @param $intervalCount
	 *
	 * @return \StripeWPFS\Price
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 */
	function createRecurringPlan( $id, $name, $currency, $interval, $intervalCount, $usageType = 'metered' ) {
		$planData = array(
			"currency" => $currency,
			"unit_amount" => "1",
			"nickname" => $name,
			"recurring" => array(
				"interval" => $interval,
				"interval_count" => $intervalCount,
				"usage_type" => $usageType,
			),
			"product_data" => array(
				"name" => $name
			),
			"lookup_key" => $id,
		);

		if ( $usageType === 'metered' ) {
			$planData["recurring"]["aggregate_usage"] = "last_ever";
		}

		$plan = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$plan = $this->remoteRequest(
				'post',
				'/price?mode=test&accountId=' . $this->testStripeAcountId,
				$planData
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$plan = $this->remoteRequest(
				'post',
				'/price?mode=live&accountId=' . $this->liveStripeAcountId,
				$planData
			);
		} else {
			$plan = json_decode( $this->stripe->prices->create( $planData )->toJSON() );
		}

		return $plan;
	}

	/**
	 * Create Stripe Product for One Time & Subscription
	 * 
	 * @param $name
	 * @param $currency
	 * @param $price
	 * @param $interval
	 * 
	 * @return \StripeWPFS\Product
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 */
	function createProduct( $name, $currency, $price, $interval = null ) {
		$currency = sanitize_text_field( $currency );
		$price    = intval( $price );
		$name     = sanitize_text_field( $name );
		$interval = sanitize_text_field( $interval );

		if ( ! in_array( $interval, [ 'day', 'week', 'month', 'year' ] ) ) {
			$interval = null;
		}

		$productData = array(
			'currency'     => $currency,
			'unit_amount'  => $price,
			'product_data' => array(
				'name' => $name
			),
		);

		if ( ! is_null( $interval ) ) {
			$productData['recurring'] = array(
				'interval' => $interval,
			);
		}

		$product = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$product = $this->remoteRequest(
				'post',
				'/price?mode=test&accountId=' . $this->testStripeAcountId,
				$productData
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$product = $this->remoteRequest(
				'post',
				'/price?mode=live&accountId=' . $this->liveStripeAcountId,
				$productData
			);
		} else {
			if ( is_null( $this->stripe ) ) {
				throw new WPFS_UserFriendlyException( 'Stripe client is not initialized' );
			}
			$product = json_decode( $this->stripe->prices->create( $productData )->toJSON() );
		}

		return $product;
	}

	/**
	 * @param $planId
	 *
	 * @return \StripeWPFS\Price|null
	 */
	public function retrievePlan( $planId ) {
		$plan = null;
		try {
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$plan = $this->remoteRequest(
					'get',
					'/price?mode=test&accountId=' . $this->testStripeAcountId . '&priceId=' . $planId
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$plan = $this->remoteRequest(
					'get',
					'/price?mode=live&accountId=' . $this->liveStripeAcountId . '&priceId=' . $planId
				);
			} else {
				$plan = json_decode( $this->stripe->prices->retrieve(
					$planId,
					array( "expand" => array( "product" ) )
				)->toJSON() );
			}
		} catch (Exception $e) {
			// plan not found, let's fall through
		}

		return $plan;
	}

	/**
	 * @param $planId
	 *
	 * @return \StripeWPFS\Collection
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrievePlansWithLookupKey( $planId ) {
		$prices = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$prices = $this->remoteRequest(
				'post',
				'/price/list?mode=test&accountId=' . $this->testStripeAcountId . '&lookupKey=' . $planId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$prices = $this->remoteRequest(
				'post',
				'/price/list?mode=live&accountId=' . $this->liveStripeAcountId . '&lookupKey=' . $planId
			);
		} else {
			$prices = json_decode( $this->stripe->prices->all( [ 
				'active' => true,
				'lookup_keys' => [ $planId ]
			] )->toJSON() );
		}

		return $prices;
	}

	public function getCustomersByEmail( $email ) {
		$customers = array();

		try {
			do {
				$params = array( 'limit' => 100, 'email' => $email );
				$last_customer = end( $customers );
				if ( $last_customer ) {
					if ( is_array( $last_customer ) )
						$params['starting_after'] = $last_customer['id'];
					else
						$params['starting_after'] = $last_customer->id;
				}
				if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
					$customer_collection = $this->remoteRequest(
						'post',
						'/customer/list?mode=test&accountId=' . $this->testStripeAcountId,
						$params
					);
					$customers = array_merge( $customers, $customer_collection->data );
				} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
					$customer_collection = $this->remoteRequest(
						'post',
						'/customer/list?mode=live&accountId=' . $this->liveStripeAcountId,
						$params
					);
					$customers = array_merge( $customers, $customer_collection->data );
				} else {
					if ( is_null( $this->stripe ) ) {
						throw new WPFS_UserFriendlyException( 'Stripe client is not initialized' );
					}
					$customer_collection = $this->stripe->customers->all( $params );
					$customers = array_merge( $customers, $customer_collection->data );
				}
			} while ( $customer_collection->has_more );
		} catch (Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while getting customers by email', $ex );

			$customers = array();
		}

		return $customers;
	}

	/**
	 * @param $params
	 * @return \StripeWPFS\Collection
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function getCustomersWithParams( $params ) {
		$customers = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$customers = $this->remoteRequest(
				'post',
				'/customer/list?mode=test&accountId=' . $this->testStripeAcountId,
				$params
			);
			$customers = $customers->data;
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$customers = $this->remoteRequest(
				'post',
				'/customer/list?mode=live&accountId=' . $this->liveStripeAcountId,
				$params
			);
			$customers = $customers->data;
		} else {
			$customers = json_decode( $this->stripe->customers->all( $params )->toJSON() );
		}
		return $customers;
	}

	/**
	 * @param $customerId
	 * @param $params
	 * @return array
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function createCustomerPortalSession( $customerId, $returnUrl ) {
		$session = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$session = $this->remoteRequest(
				'post',
				'/portal?mode=test',
				array(
					'customerId' => $customerId,
					'accountId' => $this->testStripeAcountId,
					'returnUrl' => $returnUrl
				)
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$session = $this->remoteRequest(
				'post',
				'/portal?mode=live',
				array(
					'customerId' => $customerId,
					'accountId' => $this->liveStripeAcountId,
					'returnUrl' => $returnUrl
				)
			);
		} else {
			$session = json_decode( $this->stripe->billingPortal->sessions->create( array( 'customer' => $customerId ) )->toJSON() );
		}

		return $session;
	}

	/**
	 * @param $accountId
	 * @return mixed
	 * @throws WPFS_UserFriendlyException
	 */
	public function getTestAccount( $accountId ) {
		try {
			return $this->remoteRequest( 'get', '/account?mode=test&accountId=' . $accountId );
		} catch ( Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Error while getting test account', $ex );
			throw new WPFS_UserFriendlyException( 'Error while getting test account: ' . $ex->getMessage() );
		}
	}

	/**
	 * @param $accountId
	 * @throws WPFS_UserFriendlyException
	 */
	public function getLiveAccount( $accountId ) {
		try {
			return $this->remoteRequest( 'get', '/account?mode=live&accountId=' . $accountId );
		} catch ( Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Error while getting live account', $ex );
			throw new WPFS_UserFriendlyException( 'Error while getting live account: ' . $ex->getMessage() );
		}
	}

	/**
	 * @return array|\StripeWPFS\Collection
	 */
	public function getSubscriptionPlans() {
		$plans = array();
		$params = array(
			'type' => 'recurring'
		);
		try {
			$plans = $this->getPriceList( $params );
		} catch (Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while getting subscription plans', $ex );

			$plans = array();
		}

		return $plans;
	}

	private function getPriceList( $params ) {
		$params['limit'] = 100;
		$params['include[]'] = 'total_count';
		$params['expand'] = array( 'data.product' );
		$prices = array();
		do {
			$lastPrice = end( $prices );
			if ( $lastPrice ) {
				if ( isset( $lastPrice ) && is_array( $lastPrice ) )
					$params['starting_after'] = ( isset( $lastPrice['id'] ) && ! empty( $lastPrice['id'] ) ) ? $lastPrice['id'] : null;
				else
					$params['starting_after'] = $lastPrice->id ? $lastPrice->id : null;
			}
			$priceCollection = null;
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$priceCollection = $this->remoteRequest(
					'post',
					'/price/list?mode=test&accountId=' . $this->testStripeAcountId,
					$params
				);
				$prices = array_merge( $prices, $priceCollection->data );
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$priceCollection = $this->remoteRequest(
					'post',
					'/price/list?mode=live&accountId=' . $this->liveStripeAcountId,
					$params
				);
				$prices = array_merge( $prices, $priceCollection->data );
			} else {
				$priceCollection = $this->stripe->prices->all( $params );
				$prices = array_merge( $prices, $priceCollection['data'] );
			}
		} while ( $priceCollection->has_more );

		return $prices;
	}

	/**
	 * @return array|\StripeWPFS\Collection
	 * @throws WPFS_UserFriendlyException
	 */
	public function getOnetimePrices() {
		$prices = array();
		$params = array(
			'active' => true,
			'type' => 'one_time'
		);
		try {
			$prices = $this->getPriceList( $params );
		} catch (Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while getting one-time prices', $ex );
			$prices = array();
		}
		return $prices;
	}

	/**
	 * @return array|\StripeWPFS\Collection
	 */
	public function getRecurringPrices() {
		$prices = array();
		$params = array(
			'active' => true,
			'type' => 'recurring'
		);
		try {
			$prices = $this->getPriceList( $params );
		} catch (Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while getting recurring prices', $ex );
			$prices = array();
		}
		return $prices;
	}

	/**
	 * @return array|\StripeWPFS\Collection
	 * @throws WPFS_UserFriendlyException
	 */
	public function getTaxRates() {
		$taxRates = array();
		do {
			$params = array(
				'active' => true,
				'limit' => 100,
				'include[]' => 'total_count'
			);

			$lastTaxRate = end( $taxRates );
			if ( $lastTaxRate ) {
				if ( is_array( $lastTaxRate ) )
					$params['starting_after'] = $lastTaxRate['id'];
				else
					$params['starting_after'] = $lastTaxRate->id;
			}
			$taxRateCollection = null;
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$taxRateCollection = $this->remoteRequest(
					'post',
					'/tax/rate/list?mode=test&accountId=' . $this->testStripeAcountId,
					$params
				);
				$taxRates = array_merge( $taxRates, $taxRateCollection->data );
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$taxRateCollection = $this->remoteRequest(
					'post',
					'//tax/rate/list?mode=live&accountId=' . $this->liveStripeAcountId,
					$params
				);
				$taxRates = array_merge( $taxRates, $taxRateCollection->data );
			} else {
				if ( is_null( $this->stripe ) ) {
					throw new WPFS_UserFriendlyException( 'Stripe client is not initialized' );
				}
				$taxRateCollection = json_decode( $this->stripe->taxRates->all( $params )->toJSON() );
				$taxRates = array_merge( $taxRates, $taxRateCollection->data );
			}
		} while ( $taxRateCollection->has_more );

		return $taxRates;
	}

	/**
	 * @param $code
	 *
	 * @return \StripeWPFS\Coupon
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrieveCoupon( $code ) {
		$coupons = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$coupons = $this->remoteRequest(
				'get',
				'/coupon?mode=test&accountId=' . $this->testStripeAcountId . '&code=' . $code
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$coupons = $this->remoteRequest(
				'get',
				'/coupon?mode=live&accountId=' . $this->liveStripeAcountId . '&code=' . $code
			);
		} else {
			$coupons = json_decode( $this->stripe->coupons->retrieve( $code, [ 'expand' => [ 'applies_to' ] ] )->toJSON() );
		}
		return $coupons;
	}

	/**
	 * @param $code
	 *
	 * @return \StripeWPFS\PromotionCode
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrievePromotionalCode( $code ) {
		$promotionalCodesCollection = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$promotionalCodesCollection = $this->remoteRequest(
				'get',
				'/coupon/promo?mode=test&accountId=' . $this->testStripeAcountId . '&code=' . $code
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$promotionalCodesCollection = $this->remoteRequest(
				'get',
				'/coupon/promo?mode=live&accountId=' . $this->liveStripeAcountId . '&code=' . $code
			);
		} else {
			$promotionalCodesCollection = $this->stripe->promotionCodes->all(
				[ 
					'code' => $code,
					'expand' => [ 'data.coupon.applies_to' ]
				]
			);
		}

		$result = null;

		// TODO: replace this with something else that doesn't require the stripe specific object
		$promotionalCodesCollection = \StripeWPFS\Collection::constructFrom(
			json_decode(
				json_encode( $promotionalCodesCollection ),
				true
			)
		);

		foreach ( $promotionalCodesCollection->autoPagingIterator() as $promotionCode ) {
			if ( strcasecmp( $code, $promotionCode->code ) === 0 ) {
				$result = $promotionCode;
				break;
			}
		}

		return $result;
	}

	protected function getPromotionalCode( $code ) {
		try {
			return $this->retrievePromotionalCode( $code );
		} catch (Exception $ex) {
			$this->logger->debug( __FUNCTION__, "Cannot retrieve promotional code" . $ex );
			return null;
		}
	}

	protected function getCoupon( $code ) {
		try {
			return $this->retrieveCoupon( $code );
		} catch (Exception $ex) {
			$this->logger->debug( __FUNCTION__, "Cannot retrieve coupon" . $ex );

			return null;
		}
	}

	/**
	 * @param $code string
	 * @return \StripeWPFS\Coupon|null
	 */
	public function retrieveCouponByPromotionalCodeOrCouponCode( $code ) {
		$result = null;

		try {
			$promotionalCode = $this->getPromotionalCode( $code );

			if ( ! is_null( $promotionalCode ) ) {
				if ( false == $promotionalCode->active ) {
					$result = $this->getCoupon( $code );
				} else {
					$result = $promotionalCode->coupon;
				}
			} else {
				$result = $this->getCoupon( $code );
			}
		} catch (Exception $ex) {
			$this->logger->error( __FUNCTION__, "Cannot retrieve coupon or promotional code", $ex );
		}

		return $result;
	}


	/**
	 * @param $invoiceId
	 *
	 * @return \StripeWPFS\Invoice
	 * @throws WPFS_UserFriendlyException
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 */
	function retrieveInvoice( $invoiceId ) {
		$invoice = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'get',
				'/invoice?mode=test&accountId=' . $this->testStripeAcountId . '&invoiceId=' . $invoiceId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'get',
				'/invoice?mode=live&accountId=' . $this->liveStripeAcountId . '&invoiceId=' . $invoiceId
			);
		} else {
			$invoice = json_decode( $this->stripe->invoices->retrieve( $invoiceId )->toJSON() );
		}

		return $invoice;
	}

	/**
	 * @param $paymentMethodId
	 * @param $customerName
	 * @param $customerEmail
	 * @param $metadata
	 *
	 * @return \StripeWPFS\Customer
	 *
	 * @throws StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	function createCustomerWithPaymentMethod(
		$paymentMethodId,
		$customerName,
		$customerEmail,
		$metadata,
		$taxIdType = null,
		$taxId = null,
		$billing_address = null,
		$billing_name = null,
		$shipping_address = null,
		$shipping_name = null
	) {
		$customer = array(
			'email' => $customerEmail,
		);
		if ( ! is_null( $paymentMethodId ) ) {
			$customer['payment_method'] = $paymentMethodId;
			$customer['invoice_settings']['default_payment_method'] = $paymentMethodId;
		}

		if ( ! is_null( $billing_name ) ) {
			$customer['name'] = $billing_name;
		} elseif ( ! is_null( $customerName ) ) {
			$customer['name'] = $customerName;
		}

		if ( ! is_null( $metadata ) ) {
			$customer['metadata'] = $metadata;
		}

		if ( ! empty( $taxIdType ) && ! empty( $taxId ) ) {
			$customer['tax_id_data'] = [ 
				[ 
					'type' => $taxIdType,
					'value' => $taxId
				]
			];
		}

		if ( ! empty( $billing_address ) ) {
			$customer['address'] = MM_WPFS_Utils::prepareStripeBillingAddressHashFromArray( $billing_address );
		}
		if ( ! empty( $shipping_address ) ) {
			$customer['shipping'] = [ 
				'address' => MM_WPFS_Utils::prepareStripeBillingAddressHashFromArray( $shipping_address ),
				'name' => $shipping_name
			];
		}

		$createdCustomer = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$createdCustomer = $this->remoteRequest(
				'post',
				'/customer?mode=test&accountId=' . $this->testStripeAcountId,
				$customer
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$createdCustomer = $this->remoteRequest(
				'post',
				'/customer?mode=live&accountId=' . $this->liveStripeAcountId,
				$customer
			);
		} else {
			$createdCustomer = json_decode( $this->stripe->customers->create( $customer )->toJSON() );
		}

		return $createdCustomer;
	}

	/**
	 * @param $paymentMethodId
	 * @param $customerId
	 * @param $currency
	 * @param $amount
	 * @param $capture
	 * @param $description
	 * @param $metadata - optional default null
	 * @param $stripeEmail - optional default null
	 * @param $allowRedirects - 'never' or 'always' defaults to 'never'
	 * @param $paymentMethodTypes - list of payment methods to allow defaults to ['card','link']
	 *
	 * @return stdClass
	 *
	 * @throws StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	function createPaymentIntent(
		$paymentMethodId,
		$customerId,
		$currency,
		$amount,
		$capture,
		$description,
		$metadata = null,
		$stripeEmail = null,
		$allowRedirects = 'never',
		$paymentMethodTypes = [ 'card', 'link' ]
	) {
		$paymentIntentParameters = array(
			'amount' => isset( $amount ) && ! empty( $amount ) ? $amount : 100,
			'currency' => $currency,
			'customer' => $customerId,
			'payment_method' => $paymentMethodId,
			'expand' => [ 'latest_charge' ],
		);
		if ( ! empty( $description ) ) {
			$paymentIntentParameters['description'] = $description;
		}
		if ( false === $capture ) {
			$paymentIntentParameters['capture_method'] = 'manual';
		}
		if ( isset( $stripeEmail ) ) {
			$paymentIntentParameters['receipt_email'] = $stripeEmail;
		}
		if ( isset( $metadata ) ) {
			$paymentIntentParameters['metadata'] = $metadata;
		}
		if ( isset( $paymentMethodTypes ) ) {
			if ( ! is_array( $paymentMethodTypes ) )
				$paymentIntentParameters['payment_method_types'] = json_decode( $paymentMethodTypes );
			else
				$paymentIntentParameters['payment_method_types'] = $paymentMethodTypes;
		} else {
			$paymentIntentParameters['automatic_payment_methods'] = [ 
				'enabled' => true,
				'allow_redirects' => $allowRedirects, // payment intents can be redirected e.g. for BNPL type payments so 
			];
			$paymentIntentParameters['confirm'] = true;
		}

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentIntentParameters = array_merge(
				$paymentIntentParameters,
				array(
					'validLicense' => $this->validLicense,
				)
			);
			$intent = $this->remoteRequest(
				'post',
				'/payment_intent?mode=test&accountId=' . $this->testStripeAcountId . '&api_version=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentIntentParameters = array_merge(
				$paymentIntentParameters,
				array(
					'validLicense' => $this->validLicense,
				)
			);
			$intent = $this->remoteRequest(
				'post',
				'/payment_intent?mode=live&accountId=' . $this->liveStripeAcountId . '&api_version=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);
		} else {
			// make sure to never allow redirects on non-connect flows
			$paymentIntentParameters['automatic_payment_methods']['allow_redirects'] = 'never';
			$intent = json_decode( $this->stripe->paymentIntents->create(
				apply_filters(
					'fullstripe_payment_intent_parameters',
					$paymentIntentParameters
				)
			)->toJSON() );
		}

		return $intent;
	}

	/**
	 * @throws WPFS_UserFriendlyException
	 */
	function getTestAccountLink( $accountId, $refreshUrl, $returnUrl ) {
		$params = array(
			'accountId' => $accountId,
			'refreshUrl' => $refreshUrl,
			'returnUrl' => $returnUrl,
		);

		$data = $this->remoteRequest(
			'post',
			'/account/onboarding_link?mode=test',
			$params
		);

		return $data->accountLink;
	}

	/**
	 * @throws WPFS_UserFriendlyException
	 */
	function getLiveAccountLink( $accountId, $refreshUrl, $returnUrl ) {
		$params = array(
			'accountId' => $accountId,
			'refreshUrl' => $refreshUrl,
			'returnUrl' => $returnUrl,
		);

		$data = $this->remoteRequest(
			'post',
			'/account/onboarding_link?mode=live',
			$params
		);

		return $data->accountLink;
	}

	/**
	 * @param $ctx MM_WPFS_CreateOneTimeInvoiceContext
	 * @param $options MM_WPFS_CreateOneTimeInvoiceOptions
	 * @return \StripeWPFS\Invoice
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	function createInvoiceForOneTimePayment( $ctx, $options ) {
		$invoiceParams = array(
			'customer' => $ctx->stripeCustomerId,
			'auto_advance' => $options->autoAdvance,
			'metadata' => array(
				'webhookUrl' => esc_attr( MM_WPFS_EventHandler::getWebhookEndpointURL( $this->staticContext ) ),
			)
		);

		// if we're not using connect, don't send the amount as it's not allowed by Stripe in the direct call
		// we use the amount in the cloud functions to calculate the application fee that is added to the invoice
		if ( $this->usingWpTestPlatform || $this->usingWpLivePlatform ) {
			$invoiceParams['amount'] = $ctx->amount;
		}

		$invoiceItemParams = array(
			'customer' => $ctx->stripeCustomerId,
		);

		if ( $ctx->stripePriceId !== null ) {
			$invoiceItemParams['price'] = $ctx->stripePriceId;
		} else {
			$invoiceItemParams['amount'] = $ctx->amount;
			$invoiceItemParams['currency'] = $ctx->currency;
			$invoiceItemParams['description'] = $ctx->productName;
		}

		if ( isset( $ctx->stripeCouponId ) ) {
			$invoiceItemParams['discounts'] = array(
				array( 'coupon' => $ctx->stripeCouponId )
			);
		}

		if ( $ctx->isStripeTax ) {
			$invoiceParams['automatic_tax'] = [ 
				'enabled' => true
			];
		} else {
			if ( isset( $options->taxRateIds ) && count( $options->taxRateIds ) > 0 ) {
				$invoiceItemParams['tax_rates'] = $options->taxRateIds;
			}
		}

		$invoiceItem = null;
		$createdInvoice = null;

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			// add the license key to the invoice params
			$invoiceParams = array_merge( $invoiceParams, array( 'validLicense' => $this->validLicense ) );
			// create invoice
			$createdInvoice = $this->remoteRequest(
				'post',
				'/invoice?mode=test&accountId=' . $this->testStripeAcountId . '&api_version=' . $this->userVersion,
				$invoiceParams
			);
			$invoiceItemParams['invoice'] = $createdInvoice->id;

			// create invoice item and attach to newly created invoice
			$this->remoteRequest(
				'post',
				'/invoice/item?mode=test&accountId=' . $this->testStripeAcountId,
				$invoiceItemParams
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			// add the license key to the invoice params
			$invoiceParams = array_merge( $invoiceParams, array( 'validLicense' => $this->validLicense ) );
			// create invoice
			$createdInvoice = $this->remoteRequest(
				'post',
				'/invoice?mode=live&accountId=' . $this->liveStripeAcountId . '&api_version=' . $this->userVersion,
				$invoiceParams
			);
			$invoiceItemParams['invoice'] = $createdInvoice->id;

			// create invoice item and attach to newly created invoice
			$this->remoteRequest(
				'post',
				'/invoice/item?mode=live&accountId=' . $this->liveStripeAcountId,
				$invoiceItemParams
			);
		} else {
			// We're doing it the old fashioned way; invoice item first and invoice second. The invoice item is attached to the invoice in the process.
			$invoiceItem = json_decode( $this->stripe->invoiceItems->create( $invoiceItemParams )->toJSON() );
			$createdInvoice = json_decode( $this->stripe->invoices->create( $invoiceParams )->toJSON() );
		}

		return $createdInvoice;
	}

	/**
	 * @param $ctx MM_WPFS_CreateOneTimeInvoiceContext
	 * @param $options MM_WPFS_CreateOneTimeInvoiceOptions
	 * @return \StripeWPFS\Invoice
	 */
	function createPreviewInvoiceForOneTimePayment( $ctx, $options ) {
		$invoiceParams = [];

		$address = [ 
			'country' => $ctx->taxCountry
		];
		if ( $ctx->isStripeTax ) {
			$invoiceParams['automatic_tax'] = [ 
				'enabled' => true
			];

			if ( ! empty( $ctx->taxPostalCode ) ) {
				$address['postal_code'] = $ctx->taxPostalCode;
			}
		} else {
			if ( ! empty( $ctx->taxState ) ) {
				$address['state'] = $ctx->taxState;
			}
		}
		$invoiceParams['customer_details'] = [ 
			'address' => $address
		];

		if ( $ctx->isStripeTax && ! empty( $ctx->taxIdType ) && ! empty( $ctx->taxId ) ) {
			$invoiceParams['customer_details']['tax_ids'] = [ 
				[ 
					'type' => $ctx->taxIdType,
					'value' => $ctx->taxId,
				]
			];
		}

		$itemParams = [];
		if ( $ctx->stripePriceId !== null ) {
			$itemParams['price'] = $ctx->stripePriceId;
		} else {
			$itemParams['amount'] = round( $ctx->amount );
			$itemParams['currency'] = $ctx->currency;
			$itemParams['description'] = $ctx->productName;
		}

		if ( isset( $ctx->stripeCouponId ) ) {
			$itemParams['discounts'] = array(
				array( 'coupon' => $ctx->stripeCouponId )
			);
		}

		if ( ! $ctx->isStripeTax ) {
			if ( isset( $options->taxRateIds ) && count( $options->taxRateIds ) > 0 ) {
				$itemParams['tax_rates'] = $options->taxRateIds;
			}
		}

		$invoiceParams['invoice_items'] = [ 
			$itemParams
		];

		return $this->getUpcomingInvoice( $invoiceParams );
	}

	/**
	 * @param $finalizedInvoice
	 * @param $stripePaymentMethodId
	 * @param $stripeChargeDescription
	 * @param $stripeReceiptEmailAddress
	 *
	 * @return \StripeWPFS\Invoice
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 */
	function updatePaymentIntentByInvoice(
		$finalizedInvoice,
		$stripePaymentMethodId,
		$stripeChargeDescription,
		$metadata,
		$stripeReceiptEmailAddress
	) {
		$paymentIntentParameters = array();
		if ( ! empty( $stripeChargeDescription ) ) {
			$paymentIntentParameters['description'] = $stripeChargeDescription;
		}
		if ( isset( $stripeReceiptEmailAddress ) ) {
			$paymentIntentParameters['receipt_email'] = $stripeReceiptEmailAddress;
		}
		if ( isset( $metadata ) ) {
			$paymentIntentParameters['metadata'] = $metadata;
		}

		$generatedPaymentIntent = null;
		$updatedPaymentIntent = null;
		$updatedInvoice = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$generatedPaymentIntent = $this->remoteRequest(
				'get',
				'/payment_intent?mode=test&accountId=' . $this->testStripeAcountId . '&paymentIntentId=' . $finalizedInvoice->payment_intent
			);

			$updatedPaymentIntent = $this->remoteRequest(
				'post',
				'/payment_intent/update?mode=test&accountId=' . $this->testStripeAcountId . '&paymentIntentId=' . $generatedPaymentIntent->id . '&api_version=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);

			$this->remoteRequest(
				'post',
				'/payment_intent/confirm?mode=test&accountId=' . $this->testStripeAcountId . '&paymentIntentId=' . $updatedPaymentIntent->id,
				array( 'payment_method' => $stripePaymentMethodId )
			);

			$updatedInvoice = $this->remoteRequest(
				'post',
				'/invoice/update?mode=test&accountId=' . $this->testStripeAcountId . '&invoiceId=' . $finalizedInvoice->id,
				array()
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$generatedPaymentIntent = $this->remoteRequest(
				'get',
				'/payment_intent?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentIntentId=' . $finalizedInvoice->payment_intent
			);

			$updatedPaymentIntent = $this->remoteRequest(
				'post',
				'/payment_intent/update?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentIntentId=' . $generatedPaymentIntent->id . '&api_version=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);

			$this->remoteRequest(
				'post',
				'/payment_intent/confirm?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentIntentId=' . $updatedPaymentIntent->id,
				array( 'payment_method' => $stripePaymentMethodId )
			);

			$updatedInvoice = $this->remoteRequest(
				'post',
				'/invoice/update?mode=live&accountId=' . $this->liveStripeAcountId . '&invoiceId=' . $finalizedInvoice->id,
				array()
			);
		} else {
			$generatedPaymentIntent = json_decode( $this->stripe->paymentIntents->retrieve( $finalizedInvoice->payment_intent )->toJSON() );
			$updatedPaymentIntent = json_decode( $this->stripe->paymentIntents->update(
				$generatedPaymentIntent->id,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			)->toJSON() );
			$this->stripe->paymentIntents->confirm(
				$updatedPaymentIntent->id,
				array( 'payment_method' => $stripePaymentMethodId )
			);
			$updatedInvoice = json_decode( $this->stripe->invoices->update( $finalizedInvoice->id )->toJSON() );
		}

		return $updatedInvoice;
	}

	/**
	 * @param $paymentIntentId
	 *
	 * @return \StripeWPFS\PaymentIntent
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	function retrievePaymentIntent( $paymentIntentId ) {
		$intent = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$intent = $this->remoteRequest(
				'get',
				'/payment_intent?mode=test&accountId=' . $this->testStripeAcountId . '&paymentIntentId=' . $paymentIntentId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$intent = $this->remoteRequest(
				'get',
				'/payment_intent?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentIntentId=' . $paymentIntentId
			);
		} else {
			$intent = json_decode( $this->stripe->paymentIntents->retrieve( $paymentIntentId )->toJSON() );
		}


		return $intent;
	}

	/**
	 * @param $invoiceId
	 * @param $params
	 *
	 * @return \StripeWPFS\Invoice
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 */
	public function retrieveInvoiceWithParams( $invoiceId, $params ) {
		$invoice = null;

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoice/search?mode=test&accountId=' . $this->testStripeAcountId . '&invoiceId=' . $invoiceId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoice/search?mode=live&accountId=' . $this->liveStripeAcountId . '&invoiceId=' . $invoiceId,
				$params
			);
		} else {
			$invoice = json_decode( $this->stripe->invoices->retrieve( $invoiceId, $params )->toJSON() );
		}

		return $invoice;
	}

	/**
	 * @param $sessionId
	 * @param $params
	 *
	 * @return \StripeWPFS\Checkout\Session
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrieveCheckoutSessionWithParams( $sessionId, $params ) {
		$checkoutSession = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$checkoutSession = $this->remoteRequest(
				'post',
				'/checkout/search?mode=test&accountId=' . $this->testStripeAcountId . '&sessionId=' . $sessionId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$checkoutSession = $this->remoteRequest(
				'post',
				'/checkout/search?mode=live&accountId=' . $this->liveStripeAcountId . '&sessionId=' . $sessionId,
				$params
			);
		} else {
			$checkoutSession = json_decode( $this->stripe->checkout->sessions->retrieve( $sessionId, $params )->toJSON() );
		}

		return $checkoutSession;
	}

	/**
	 * @param $paymentMethodId
	 *
	 * @return \StripeWPFS\PaymentMethod
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrievePaymentMethod( $paymentMethodId ) {
		$paymentMethod = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_method?mode=test&accountId=' . $this->testStripeAcountId . '&paymentMethodId=' . $paymentMethodId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_method?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentMethodId=' . $paymentMethodId
			);
		} else {
			$paymentMethod = json_decode( $this->stripe->paymentMethods->retrieve( $paymentMethodId )->toJSON() );
		}

		return $paymentMethod;
	}

	/**
	 * @param $eventID
	 *
	 * @return \StripeWPFS\Event
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrieveEvent( $eventID ) {
		$event = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$event = $this->remoteRequest(
				'get',
				'/event?mode=test&accountId=' . $this->testStripeAcountId . '&eventId=' . $eventID
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$event = $this->remoteRequest(
				'get',
				'/event?mode=live&accountId=' . $this->liveStripeAcountId . '&eventId=' . $eventID
			);
		} else {
			$event = json_decode( $this->stripe->events->retrieve( $eventID )->toJSON() );
		}

		return $event;
	}

	/**
	 * @return \StripeWPFS\Invoice
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function createSetupIntent() {
		$params = array(
			'usage' => 'off_session',
			'metadata' => array(
				'webhookUrl' => esc_attr( MM_WPFS_EventHandler::getWebhookEndpointURL( $this->staticContext ) ),
			),
			'automatic_payment_methods' => array(
				'enabled' => true,
				'allow_redirects' => 'never', //setup intents do not support redirects
			),
		);

		$setupIntent = null;
		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$setupIntent = $this->remoteRequest(
				'post',
				'/setup_intent?mode=' . $this->apiMode . '&accountId=' . $accountId,
				$params
			);
		} else {
			$setupIntent = json_decode( $this->stripe->setupIntents->create()->toJSON() );
		}

		return $setupIntent;
	}


	/**
	 * @param $stripePaymentMethodId
	 *
	 * @return \StripeWPFS\SetupIntent
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function createSetupIntentWithPaymentMethod( $stripePaymentMethodId ) {
		$params = array(
			'usage' => 'off_session',
			'payment_method' => $stripePaymentMethodId,
			'confirm' => false,
			'metadata' => array(
				'webhookUrl' => esc_attr( MM_WPFS_EventHandler::getWebhookEndpointURL( $this->staticContext ) ),
			),
			'automatic_payment_methods' => array(
				'enabled' => true,
				'allow_redirects' => 'never', //setup intents do not support redirects
			),
		);

		$intent = null;
		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$intent = $this->remoteRequest(
				'post',
				'/setup_intent?mode=' . $this->apiMode . '&accountId=' . $accountId,
				$params
			);
		} else {
			$intent = json_decode( $this->stripe->setupIntents->create( $params )->toJSON() );
		}

		return $intent;
	}

	/**
	 * @param $stripeSetupIntentId
	 *
	 * @return \StripeWPFS\SetupIntent
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 */
	function retrieveSetupIntent( $stripeSetupIntentId ) {
		$intent = null;
		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$intent = $this->remoteRequest(
				'get',
				'/setup_intent?mode=' . $this->apiMode . '&accountId=' . $accountId . '&setupIntentId=' . $stripeSetupIntentId
			);
		} else {
			$intent = json_decode( $this->stripe->setupIntents->retrieve( $stripeSetupIntentId )->toJSON() );
		}

		return $intent;
	}

	/**
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	function attachPaymentMethodToStripeCustomer( $stripeCustomer, $currentPaymentMethod ) {
		$attachedPaymentMethod = null;

		if ( isset( $stripeCustomer ) && isset( $currentPaymentMethod ) ) {
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$attachedPaymentMethod = $this->remoteRequest(
					'get',
					'/payment_method/attach?mode=test&accountId=' . $this->testStripeAcountId . '&paymentMethodId=' . $currentPaymentMethod->id . '&customerId=' . $stripeCustomer->id
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$attachedPaymentMethod = $this->remoteRequest(
					'get',
					'/payment_method/attach?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentMethodId=' . $currentPaymentMethod->id . '&customerId=' . $stripeCustomer->id
				);
			} else {
				if ( is_null( $currentPaymentMethod->customer ) ) {
					$this->stripe->paymentMethods->attach(
						$currentPaymentMethod->id,
						array( 'customer' => $stripeCustomer->id )
					);
					$this->logger->debug( __FUNCTION__, 'PaymentMethod attached.' );
				}
				$attachedPaymentMethod = $currentPaymentMethod;
			}
		}

		return $attachedPaymentMethod;
	}

	/**
	 * Attaches the given PaymentMethod to the given Customer if the Customer do not have an identical PaymentMethod
	 * by card fingerprint.
	 *
	 * @param \StripeWPFS\Customer $stripeCustomer
	 * @param \StripeWPFS\PaymentMethod $currentPaymentMethod
	 * @param bool $setToDefault
	 * @throws StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 * @return \StripeWPFS\PaymentMethod the attached PaymentMethod or the existing one
	 */
	function attachPaymentMethodToCustomerIfMissing( $stripeCustomer, $currentPaymentMethod, $setToDefault = false ) {
		$attachedPaymentMethod = null;

		if ( isset( $stripeCustomer ) && isset( $currentPaymentMethod ) ) {
			// WPFS-983: tnagy find existing PaymentMethod with identical fingerprint and reuse it
			$existingStripePaymentMethod = $this->findExistingPaymentMethodByFingerPrintAndExpiry(
				$stripeCustomer,
				$currentPaymentMethod->card->fingerprint,
				$currentPaymentMethod->card->exp_year,
				$currentPaymentMethod->card->exp_month
			);
			if ( isset( $existingStripePaymentMethod ) ) {
				$this->logger->debug(
					__FUNCTION__,
					'PaymentMethod with identical card fingerprint exists, won\'t attach.'
				);

				$attachedPaymentMethod = $existingStripePaymentMethod;
			} else {
				if ( is_null( $currentPaymentMethod->customer ) ) {
					$this->attachPaymentMethodToStripeCustomer( $stripeCustomer, $currentPaymentMethod );

					$this->logger->debug( __FUNCTION__, 'PaymentMethod ' . $currentPaymentMethod->id . ' attached.' );
				}
				$attachedPaymentMethod = $currentPaymentMethod;
			}
			if ( $setToDefault ) {
				$updateCustomerBody = array(
					'invoice_settings' => array(
						'default_payment_method' => $attachedPaymentMethod->id
					)
				);
				$this->updateCustomerDetails( $stripeCustomer, $updateCustomerBody );
				$this->logger->debug( __FUNCTION__, 'Default PaymentMethod updated.' );
			}

		}

		return $attachedPaymentMethod;
	}

	/**
	 * Find a Customer's PaymentMethod by fingerprint if exists.
	 *
	 * @param \StripeWPFS\Customer $stripeCustomer
	 * @param string $paymentMethodCardFingerPrint
	 * @param $expiryYear
	 * @param $expiryMonth
	 *
	 * @return null|\StripeWPFS\PaymentMethod the existing PaymentMethod
	 * @throws StripeWPFS\Exception\ApiErrorException
	 */
	public function findExistingPaymentMethodByFingerPrintAndExpiry(
		$stripeCustomer,
		$paymentMethodCardFingerPrint,
		$expiryYear,
		$expiryMonth
	) {
		if ( empty( $paymentMethodCardFingerPrint ) ) {
			return null;
		}

		$paymentMethodBody = array(
			'customer' => $stripeCustomer->id,
			'type' => 'card'
		);

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethods = $this->remoteRequest(
				'post',
				'/payment_method/list?mode=test&accountId=' . $this->testStripeAcountId,
				$paymentMethodBody
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethods = $this->remoteRequest(
				'post',
				'/payment_method/list?mode=live&accountId=' . $this->liveStripeAcountId,
				$paymentMethodBody
			);
		} else {
			// fallback to using the Stripe API directly
			$paymentMethods = json_decode( $this->stripe->paymentMethods->all( $paymentMethodBody )->toJSON() );
		}
		$existingPaymentMethod = null;
		if ( isset( $paymentMethods ) && isset( $paymentMethods->data ) ) {
			foreach ( $paymentMethods->data as $paymentMethod ) {
				/**
				 * @var \StripeWPFS\PaymentMethod $paymentMethod
				 */
				if ( is_null( $existingPaymentMethod ) ) {
					if ( isset( $paymentMethod ) && isset( $paymentMethod->card ) && isset( $paymentMethod->card->fingerprint ) ) {
						if (
							$paymentMethod->card->fingerprint == $paymentMethodCardFingerPrint &&
							$paymentMethod->card->exp_year == $expiryYear &&
							$paymentMethod->card->exp_month == $expiryMonth
						) {
							$existingPaymentMethod = $paymentMethod;
						}
					}
				}
			}
		}

		return $existingPaymentMethod;
	}

	/**
	 * @param $subscriptionId string
	 *
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 */
	public function activateCancelledSubscription( $subscriptionId ) {
		$subscription = $this->retrieveSubscription( $subscriptionId );

		do_action( MM_WPFS::ACTION_NAME_BEFORE_SUBSCRIPTION_ACTIVATION, $subscriptionId );
		if ( is_array( $subscription ) ) {
			$subscription['cancel_at_period_end'] = false;
		} else {
			$subscription->cancel_at_period_end = false;
		}
		$this->updateSubscription($subscription);
		// $this->stripe->subscriptions->update( $subscriptionId, $subscription );

		do_action( MM_WPFS::ACTION_NAME_AFTER_SUBSCRIPTION_ACTIVATION, $subscriptionId );
	}

	/**
	 * @param $stripeSubscriptionId
	 */
	private function fireBeforeSubscriptionCancellationAction( $stripeSubscriptionId ) {
		do_action( MM_WPFS::ACTION_NAME_BEFORE_SUBSCRIPTION_CANCELLATION, $stripeSubscriptionId );
	}

	/**
	 * @param $stripeSubscriptionId
	 */
	private function fireAfterSubscriptionCancellationAction( $stripeSubscriptionId ) {
		do_action( MM_WPFS::ACTION_NAME_AFTER_SUBSCRIPTION_CANCELLATION, $stripeSubscriptionId );
	}

	/**
	 * @param $stripeCustomerId
	 * @param $stripeSubscriptionId
	 * @param bool $atPeriodEnd
	 *
	 * @return bool
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function cancelSubscription( $stripeCustomerId, $stripeSubscriptionId, $atPeriodEnd = false ) {
		if ( ! empty( $stripeSubscriptionId ) ) {
			$subscription = $this->retrieveSubscription( $stripeSubscriptionId );

			if ( $subscription ) {
				$this->fireBeforeSubscriptionCancellationAction( $stripeSubscriptionId );

				/** @noinspection PhpUnusedLocalVariableInspection */
				$cancellationResult = null;
				if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
					if ( $atPeriodEnd ) {
						$this->remoteRequest(
							'post',
							'/subscription/update?mode=test&accountId=' . $this->testStripeAcountId . '&subscriptionId=' . $stripeSubscriptionId,
							array(
								'cancel_at_period_end' => true
							)
						);
					} else {
						$cancellationResult = $this->remoteRequest(
							'get',
							'/subscription/cancel?mode=test&accountId=' . $this->testStripeAcountId . '&subscriptionId=' . $stripeSubscriptionId
						);
					}
				} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
					if ( $atPeriodEnd ) {
						$this->remoteRequest(
							'post',
							'/subscription/update?mode=live&accountId=' . $this->liveStripeAcountId . '&subscriptionId=' . $stripeSubscriptionId,
							array(
								'cancel_at_period_end' => true
							)
						);
					} else {
						$cancellationResult = $this->remoteRequest(
							'get',
							'/subscription/cancel?mode=live&accountId=' . $this->liveStripeAcountId . '&subscriptionId=' . $stripeSubscriptionId
						);
					}
				} else {
					if ( $atPeriodEnd ) {
						$cancellationResult = json_decode( $this->stripe->subscriptions->update(
							$stripeSubscriptionId,
							array(
								'cancel_at_period_end' => true
							)
						)->toJSON() );
					} else {
						$cancellationResult = $this->stripe->subscriptions->cancel( $stripeSubscriptionId );
					}
				}

				$this->fireAfterSubscriptionCancellationAction( $stripeSubscriptionId );

				if ( isset( $cancellationResult ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param $params array
	 *
	 * @return \StripeWPFS\Collection
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function listSubscriptionsWithParams( $params ) {
		$subscriptions = null;

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$subscriptions = $this->remoteRequest(
				'post',
				'/subscription/list?mode=test&accountId=' . $this->testStripeAcountId,
				$params
			);
			$subscriptions = $subscriptions->data;
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$subscriptions = $this->remoteRequest(
				'post',
				'/subscription/list?mode=live&accountId=' . $this->liveStripeAcountId,
				$params
			);
			$subscriptions = $subscriptions->data;
		} else {
			$subscriptions = json_decode( $this->stripe->subscriptions->all( $params )->toJSON() );
		}
		return $subscriptions;
	}

	/**
	 * @param $params array
	 *
	 * @return \StripeWPFS\Collection
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function listInvoicesWithParams( $params ) {
		$invoices = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoices = $this->remoteRequest(
				'post',
				'/invoice/list?mode=test&accountId=' . $this->testStripeAcountId,
				$params
			);
			$invoices = $invoices->data;
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoices = $this->remoteRequest(
				'post',
				'/invoice/list?mode=live&accountId=' . $this->liveStripeAcountId,
				$params
			);
			$invoices = $invoices->data;
		} else {
			$invoices = json_decode( $this->stripe->invoices->all( $params )->toJSON() );
			$invoices = $invoices->data;
		}
		return $invoices;
	}

	/**
	 * @param $params array
	 *
	 * @return \StripeWPFS\Collection
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function listPaymentMethodsWithParams( $params ) {
		$paymentMethods = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethods = $this->remoteRequest(
				'post',
				'/payment_method/list?mode=test&accountId=' . $this->testStripeAcountId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethods = $this->remoteRequest(
				'post',
				'/payment_method/list?mode=live&accountId=' . $this->liveStripeAcountId,
				$params
			);
		} else {
			$paymentMethods = json_decode( $this->stripe->paymentMethods->all( $params )->toJSON() );
		}
		return $paymentMethods;
	}

	/**
	 * @param $subscriptionID
	 *
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	function retrieveSubscription( $subscriptionID ) {
		$subscription = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$subscription = $this->remoteRequest(
				'get',
				'/subscription?mode=test&accountId=' . $this->testStripeAcountId . '&subscriptionId=' . $subscriptionID
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$subscription = $this->remoteRequest(
				'get',
				'/subscription?mode=live&accountId=' . $this->liveStripeAcountId . '&subscriptionId=' . $subscriptionID
			);
		} else {
			$subscription = json_decode( $this->stripe->subscriptions->retrieve( $subscriptionID )->toJSON() );
		}
		return $subscription;
	}

	/**
	 * @param $subscriptionId string
	 * @param $params array
	 *
	 * @return \StripeWPFS\Subscription
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrieveSubscriptionWithParams( $subscriptionId, $params ) {
		$stripeSubscription = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscription/search?mode=test&accountId=' . $this->testStripeAcountId . '&subscriptionId=' . $subscriptionId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscription/search?mode=live&accountId=' . $this->liveStripeAcountId . '&subscriptionId=' . $subscriptionId,
				$params
			);
		} else {
			$stripeSubscription = json_decode( $this->stripe->subscriptions->retrieve(
				$subscriptionId,
				$params
			)->toJSON() );
		}

		return $stripeSubscription;
	}

	/**
	 * @param $stripeSubscriptionId
	 * @param $newPlanId
	 * @param $newQuantity
	 */
	protected function fireBeforeSubscriptionUpdateAction( $stripeSubscriptionId, $newPlanId, $newQuantity ) {
		$params = [ 
			'stripeSubscriptionId' => $stripeSubscriptionId,
			'planId' => $newPlanId,
			'quantity' => $newQuantity
		];

		do_action( MM_WPFS::ACTION_NAME_BEFORE_SUBSCRIPTION_UPDATE, $params );
	}

	/**
	 * @param $stripeSubscriptionId
	 * @param $newPlanId
	 * @param $newQuantity
	 */
	protected function fireAfterSubscriptionUpdateAction( $stripeSubscriptionId, $newPlanId, $newQuantity ) {
		$params = [ 
			'stripeSubscriptionId' => $stripeSubscriptionId,
			'planId' => $newPlanId,
			'quantity' => $newQuantity
		];

		do_action( MM_WPFS::ACTION_NAME_AFTER_SUBSCRIPTION_UPDATE, $params );
	}

	/**
	 * @param $stripeCustomerId
	 * @param $stripeSubscriptionId
	 * @param $planId
	 * @param $newPlanQuantity
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 * @return bool
	 */
	public function updateSubscriptionPlanAndQuantity(
		$stripeCustomerId,
		$stripeSubscriptionId,
		$planId,
		$planQuantity = null
	) {
		if ( ! empty( $stripeSubscriptionId ) && ! empty( $planId ) ) {
			/* @var $subscription \StripeWPFS\Subscription */
			$subscription = $this->retrieveSubscription( $stripeSubscriptionId );

			if ( ! empty( $planQuantity ) && is_numeric( $planQuantity ) ) {
				$newPlanQuantity = intval( $planQuantity );
			} else {
				$newPlanQuantity = $subscription->quantity;
			}

			if ( isset( $subscription ) ) {
				$parameters = array();
				$performUpdate = false;
				$planUpdated = false;
				// tnagy update subscription plan
				if ( $subscription->plan != $planId ) {
					$parameters = array_merge( $parameters, array( 'plan' => $planId ) );
					$planUpdated = true;
					$performUpdate = true;
				}
				// tnagy update subscription quantity
				$allowMultipleSubscriptions = false;
				if ( isset( $subscription->metadata ) && isset( $subscription->metadata->allow_multiple_subscriptions ) ) {
					$allowMultipleSubscriptions = boolval( $subscription->metadata->allow_multiple_subscriptions );
				}
				$minimumQuantity = MM_WPFS_Utils::getMinimumPlanQuantityOfSubscription( $subscription );
				$maximumQuantity = MM_WPFS_Utils::getMaximumPlanQuantityOfSubscription( $subscription );
				if ( $allowMultipleSubscriptions ) {
					if ( $minimumQuantity > 0 && $newPlanQuantity < $minimumQuantity ) {
						throw new Exception(
							sprintf(
								/* translators: Error message displayed when subscriber tries to set a quantity for a subscription which is beyond allowed value */
								__(
									"Subscription quantity '%d' is not allowed for this subscription!",
									'wp-full-stripe-free'
								),
								$newPlanQuantity
							)
						);
					}
					if ( $maximumQuantity > 0 && $newPlanQuantity > $maximumQuantity ) {
						throw new Exception(
							sprintf(
								/* translators: Error message displayed when subscriber tries to set a quantity for a subscription which is over allowed value */
								__(
									"Subscription quantity '%d' is not allowed for this subscription!",
									'wp-full-stripe-free'
								),
								$newPlanQuantity
							)
						);
					}
					if ( $subscription->quantity != intval( $newPlanQuantity ) || $planUpdated ) {
						$parameters = array_merge( $parameters, array( 'quantity' => $newPlanQuantity ) );
						$performUpdate = true;
					}
				} elseif ( $newPlanQuantity > 1 ) {
					throw new Exception(
						/* translators: Error message displayed when subscriber tries to set a quantity for a
						 * subscription where quantity other than one is not allowed.
						 */
						__( 'Quantity update is not allowed for this subscription!', 'wp-full-stripe-free' )
					);
				}
			} else {
				throw new Exception(
					sprintf(
						/* translators: Error message displayed when a subscription is not found.
						 * p1: Subscription identifier
						 */
						__( "Subscription '%s' not found!", 'wp-full-stripe-free' ),
						$stripeSubscriptionId
					)
				);
			}
			if ( $performUpdate ) {
				$this->fireBeforeSubscriptionUpdateAction( $stripeSubscriptionId, $planId, $newPlanQuantity );
				if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
					$this->remoteRequest(
						'post',
						'/subscription/update?mode=test&accountId=' . $this->testStripeAcountId . '&subscriptionId=' . $stripeSubscriptionId,
						$parameters
					);
				} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
					$this->remoteRequest(
						'post',
						'/subscription/update?mode=live&accountId=' . $this->liveStripeAcountId . '&subscriptionId=' . $stripeSubscriptionId,
						$parameters
					);
				} else {
					$this->stripe->subscriptions->update( $stripeSubscriptionId, $parameters );
				}
				$this->fireAfterSubscriptionUpdateAction( $stripeSubscriptionId, $planId, $newPlanQuantity );
			}

			return true;
		} else {
			// This is an internal error, no need to localize it
			throw new Exception( 'Invalid parameters!' );
		}
	}

	/**
	 * This seems to not be used anywhere. maybe a leftover from the original code?
	 * can probably be removed
	 */
	function getProducts( $associativeArray = false, $productIds = null ) {
		$products = array();
		try {
			$params = array(
				'limit' => 100,
				'include[]' => 'total_count'
			);
			if ( ! is_null( $productIds ) && count( $productIds ) > 0 ) {
				$params['ids'] = $productIds;
			}
			$params = array( 'active' => 'false', 'limit' => 100 );
			$productCollection = null;
			// if test mode is enabled and WP test platform is used, get products from Google Cloud Functions
			if (
				$this->apiMode === 'test' &&
				$this->usingWpTestPlatform
			) {
				$productCollection = $this->remoteRequest(
					'get',
					'/product/list?mode=test&accountId=' . $this->testStripeAcountId
				);
				$productCollection = $productCollection->data;
			} elseif (
				$this->apiMode === 'live' &&
				$this->usingWpLivePlatform
			) {
				$productCollection = $this->remoteRequest(
					'get',
					'/product/list?mode=live&accountId=' . $this->liveStripeAcountId
				);
				$productCollection = $productCollection->data;
			} else {
				$productCollection = json_decode( $this->stripe->products->all( $params )->toJSON() );
			}

			// this shouldn't work, but it does?
			foreach ( $productCollection->autoPagingIterator() as $product ) {
				if ( $associativeArray ) {
					$products[ $product->id ] = $product;
				} else {
					array_push( $products, $product );
				}
			}

			// MM_WPFS_Utils::log( 'params=' . print_r( $params, true ) );
			// MM_WPFS_Utils::log( 'productCollection=' . print_r( $productCollection, true ) );

		} catch (Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while getting products', $ex );

			$products = array();
		}

		return $products;
	}

	/**
	 * @param $chargeId
	 *
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	function captureCharge( $chargeId ) {
		$charge = null;

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$charge = $this->remoteRequest(
				'get',
				'/charge?mode=test&accountId=' . $this->testStripeAcountId . '&chargeId=' . $chargeId
			);

			if ( isset( $charge ) ) {
				return $this->remoteRequest(
					'get',
					'/charge/capture?mode=test&accountId=' . $this->testStripeAcountId . '&chargeId=' . $chargeId
				);
			}

		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$charge = $this->remoteRequest(
				'get',
				'/charge?mode=live&accountId=' . $this->liveStripeAcountId . '&chargeId=' . $chargeId
			);

			if ( isset( $charge ) ) {
				return $this->remoteRequest(
					'get',
					'/charge/capture?mode=live&accountId=' . $this->liveStripeAcountId . '&chargeId=' . $chargeId
				);
			}
		} else {
			$charge = json_decode( $this->stripe->charges->retrieve( $chargeId )->toJSON() );
		}

		return $charge;
	}

	public function getLatestCharge( $paymentIntent ) {
		if ( is_string( $paymentIntent->latest_charge ) ) {
			$charge = null;
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$charge = $this->remoteRequest(
					'get',
					'/charge?mode=test&accountId=' . $this->testStripeAcountId . '&chargeId=' . $paymentIntent->latest_charge
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$charge = $this->remoteRequest(
					'get',
					'/charge?mode=live&accountId=' . $this->liveStripeAcountId . '&chargeId=' . $paymentIntent->latest_charge
				);
			} else {
				$charge = json_decode( $this->stripe->charges->retrieve( $paymentIntent->latest_charge )->toJSON() );
			}
			return $charge;
		} else {
			return $paymentIntent->latest_charge;
		}
	}

	/**
	 * @param $paymentIntentId
	 *
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function capturePaymentIntent( $paymentIntentId ) {
		$paymentIntent = $this->retrievePaymentIntent( $paymentIntentId );
		if ( isset( $paymentIntent ) ) {
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				return $this->remoteRequest(
					'get',
					'/payment_intent/capture?mode=test&accountId=' . $this->testStripeAcountId . '&paymentIntentId=' . $paymentIntent->id
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				return $this->remoteRequest(
					'get',
					'/payment_intent/capture?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentIntentId=' . $paymentIntent->id
				);
			} else {
				return json_decode( $this->stripe->paymentIntents->capture( $paymentIntentId )->toJSON() );
			}
		}

		return $paymentIntent;
	}

	/**
	 * @param $chargeId
	 *
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	function refundCharge( $chargeId ) {
		$refund = null;
		$refundBody = array(
			'charge' => $chargeId->id ? $chargeId->id : $chargeId
		);

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$refund = $this->remoteRequest(
				'post',
				'/charge/refund?mode=test&accountId=' . $this->testStripeAcountId,
				$refundBody
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$refund = $this->remoteRequest(
				'post',
				'/charge/refund?mode=live&accountId=' . $this->liveStripeAcountId,
				$refundBody
			);
		} else {
			$refund = json_decode( $this->stripe->refunds->create( [ 'charge' => $chargeId ] )->toJSON() );
		}
		return $refund;
	}

	/**
	 * @param $paymentIntentId
	 *
	 * @return \StripeWPFS\PaymentIntent|\StripeWPFS\Refund
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function cancelOrRefundPaymentIntent( $paymentIntentId ) {
		$paymentIntent = $this->retrievePaymentIntent( $paymentIntentId );
		if ( isset( $paymentIntent ) ) {
			/* @var $paymentIntent \StripeWPFS\PaymentIntent */
			if (
				\StripeWPFS\PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD === $paymentIntent->status
				|| \StripeWPFS\PaymentIntent::STATUS_REQUIRES_CAPTURE === $paymentIntent->status
				|| \StripeWPFS\PaymentIntent::STATUS_REQUIRES_CONFIRMATION === $paymentIntent->status
				|| \StripeWPFS\PaymentIntent::STATUS_REQUIRES_ACTION === $paymentIntent->status
			) {
				if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
					return $this->remoteRequest(
						'get',
						'/payment_intent/cancel?mode=test&accountId=' . $this->testStripeAcountId . '&paymentIntentId=' . $paymentIntent->id
					);
				} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
					return $this->remoteRequest(
						'get',
						'/payment_intent/cancel?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentIntentId=' . $paymentIntent->id
					);
				} else {
					return json_decode( $this->stripe->paymentIntents->cancel( $paymentIntentId )->toJSON() );
				}
			} elseif (
				\StripeWPFS\PaymentIntent::STATUS_PROCESSING === $paymentIntent->status
				|| \StripeWPFS\PaymentIntent::STATUS_SUCCEEDED === $paymentIntent->status
			) {
				return $this->refundCharge( $paymentIntent->latest_charge );
			}
		}
		return $paymentIntent;
	}

	/**
	 * Update payment intent with metadata and description
	 *
	 * @param $paymentIntent
	 * @param bool $includeAmount
	 * @param $stripeReceiptEmailAddress
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function updatePaymentIntent( $paymentIntent, $includeAmount = false, $stripeReceiptEmailAddress = null ) {
		$updateIntentBody = array(
			"metadata" => $paymentIntent->metadata,
			"description" => $paymentIntent->description,
			"validLicense" => $this->validLicense,
		);

		if ( isset( $stripeReceiptEmailAddress ) ) {
			$updateIntentBody['receipt_email'] = $stripeReceiptEmailAddress;
		}

		if ( $includeAmount && isset( $paymentIntent->amount ) ) {
			$updateIntentBody["amount"] = $paymentIntent->amount;
		}

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$this->remoteRequest(
				'post',
				'/payment_intent/update?mode=test&accountId=' . $this->testStripeAcountId . '&paymentIntentId=' . $paymentIntent->id . '&api_version=' . $this->userVersion,
				$updateIntentBody
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$this->remoteRequest(
				'post',
				'/payment_intent/update?mode=live&accountId=' . $this->liveStripeAcountId . '&paymentIntentId=' . $paymentIntent->id . '&api_version=' . $this->userVersion,
				$updateIntentBody
			);
		} else {
			// update the payment intent using the stripe API
			$params = array(
				'metadata' => $paymentIntent->metadata,
				'description' => $paymentIntent->description,
			);
			$this->stripe->paymentIntents->update( $paymentIntent->id, $params );
		}
	}

	/**
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function updateCustomer( $stripeCustomer ) {
		$address = null;
		$shipping = null;
		// trying to deal with the different types of objects the customer and/or the address can have
		if ( is_object( $stripeCustomer->address ) && get_class( $stripeCustomer->address ) === 'stdClass' ) {
			$address = json_decode( json_encode( $stripeCustomer->address ), true );
		} elseif ( is_array( $stripeCustomer->address ) ) {
			$address = $stripeCustomer->address;
		} elseif ( isset( $stripeCustomer->address ) ) {
			$address = $stripeCustomer->address->toArray();
		}
		if ( is_object( $stripeCustomer->shipping ) && get_class( $stripeCustomer->shipping ) === 'stdClass' ) {
			$shipping = json_decode( json_encode( $stripeCustomer->shipping ), true );
		} elseif ( is_array( $stripeCustomer->shipping ) ) {
			$shipping = $stripeCustomer->shipping;
		} elseif ( isset( $stripeCustomer->shipping ) ) {
			$shipping = $stripeCustomer->shipping->toArray();
		}

		// update the customer using the stripe API
		$params = array(
			'address' => $address,
			'name' => $stripeCustomer->name,
			'shipping' => $shipping,
			'description' => $stripeCustomer->description,
		);
		$this->updateCustomerDetails( $stripeCustomer, $params );
	}

	/**
	 * Update subscription metadata
	 *
	 * @param $stripeSubscription
	 * @throws WPFS_UserFriendlyException
	 */
	public function updateSubscription( $stripeSubscription ) {
		// we're just updating the metadata at this point
		// also handle cancellation updates

		$subscriptionMetadata = array(
			'metadata' => $stripeSubscription->metadata,
			'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end
		);

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$this->remoteRequest(
				'post',
				'/subscription/update?mode=test&accountId=' . $this->testStripeAcountId . '&subscriptionId=' . $stripeSubscription->id,
				$subscriptionMetadata
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$this->remoteRequest(
				'post',
				'/subscription/update?mode=live&accountId=' . $this->liveStripeAcountId . '&subscriptionId=' . $stripeSubscription->id,
				$subscriptionMetadata
			);
		} else {
			// update the subscription using the stripe API
			$this->stripe->subscriptions->update( $stripeSubscription->id, $subscriptionMetadata );
		}
	}

	/**
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function confirmSetupIntent( $setupIntent ) {

		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$setupIntent = $this->remoteRequest(
				'get',
				'/setup_intent/confirm?mode=' . $this->apiMode . '&accountId=' . $accountId . '&setupIntentId=' . $setupIntent->id . '&paymentMethodId=' . $setupIntent->payment_method
			);
		} else {
			// confirm the setup intent using the stripe API
			$setupIntent = json_decode( $this->stripe->setupIntents->confirm(
				$setupIntent->id,
				[ 'payment_method' => $setupIntent->payment_method ]
			)->toJSON() );
		}

		return $setupIntent;
	}


	/**
	 * Confirms a payment intent with the provided payment intent ID and payment method ID.
	 *
	 * @param string $paymentIntentId The ID of the payment intent.
	 * @param string $paymentMethodId The ID of the payment method.
	 */
	public function confirmPaymentIntent( $paymentIntentId, $paymentMethodId ) {
		$paymentIntent = null;
		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$paymentIntent = $this->remoteRequest(
				'get',
				'/payment_intent/confirm?mode=' . $this->apiMode . '&accountId=' . $accountId . '&paymentIntentId=' . $paymentIntentId . '&paymentMethodId=' . $paymentMethodId
			);
		} else {
			// confirm the setup intent using the stripe API
			$paymentIntent = json_decode( $this->stripe->setupIntents->confirm(
				$paymentIntentId,
				[ 'payment_method' => $paymentMethodId ]
			)->toJSON() );
		}
		return $paymentIntent;
	}

	/**
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function updateCustomerBillingAddressByPaymentMethod( $stripeCustomer, $stripePaymentMethod ) {
		if ( isset( $stripeCustomer ) && isset( $stripePaymentMethod ) ) {
			$address = $this->fetchBillingAddressFromPaymentMethod( $stripePaymentMethod );
			if ( count( $address ) > 0 ) {
				$addressBody = array(
					'address' => $address
				);
				$this->updateCustomerDetails( $stripeCustomer, $addressBody );
			}
		}
	}

	/**
	 * @param $stripePaymentMethod
	 *
	 * @return array
	 */
	private function fetchBillingAddressFromPaymentMethod( $stripePaymentMethod ) {
		$address = array();
		if (
			isset( $stripePaymentMethod->billing_details )
			&& isset( $stripePaymentMethod->billing_details->address )
			&& $this->isRealBillingAddressInPaymentMethod( $stripePaymentMethod )
		) {
			$billingDetailsAddress = $stripePaymentMethod->billing_details->address;
			if ( isset( $billingDetailsAddress->city ) ) {
				$address['city'] = $billingDetailsAddress->city;

			}
			if ( isset( $billingDetailsAddress->country ) ) {
				$address['country'] = $billingDetailsAddress->country;

			}
			if ( isset( $billingDetailsAddress->line1 ) ) {
				$address['line1'] = $billingDetailsAddress->line1;

			}
			if ( isset( $billingDetailsAddress->line2 ) ) {
				$address['line2'] = $billingDetailsAddress->line2;

			}
			if ( isset( $billingDetailsAddress->postal_code ) ) {
				$address['postal_code'] = $billingDetailsAddress->postal_code;

			}
			if ( isset( $billingDetailsAddress->state ) ) {
				$address['state'] = $billingDetailsAddress->state;

				return $address;

			}

			return $address;
		}

		return $address;
	}

	private function isRealBillingAddressInPaymentMethod( $stripePaymentMethod ) {
		$res = false;

		$billingDetailsAddress = $stripePaymentMethod->billing_details->address;
		if (
			! empty( $billingDetailsAddress->city )
			&& ! empty( $billingDetailsAddress->country )
			&& ! empty( $billingDetailsAddress->line1 )
		) {
			$res = true;
		}

		return $res;
	}

	/**
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function updateCustomerShippingAddressByPaymentMethod( $stripeCustomer, $stripePaymentMethod ) {
		if ( isset( $stripeCustomer ) && isset( $stripePaymentMethod ) ) {
			$address = $this->fetchBillingAddressFromPaymentMethod( $stripePaymentMethod );
			if ( count( $address ) > 0 ) {
				$addressBody = array(
					'shipping' => array(
						'address' => $address
					)
				);
				$this->updateCustomerDetails( $stripeCustomer, $addressBody );
			}
		}
	}

	private function updateCustomerDetails( $stripeCustomer, $params ) {
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$this->remoteRequest(
				'post',
				'/customer/update?mode=test&accountId=' . $this->testStripeAcountId . '&customerId=' . $stripeCustomer->id,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$this->remoteRequest(
				'post',
				'/customer/update?mode=live&accountId=' . $this->liveStripeAcountId . '&customerId=' . $stripeCustomer->id,
				$params
			);
		} else {
			$this->stripe->customers->update( $stripeCustomer->id, $params );
		}
	}

	/**
	 * @param $parameters array
	 *
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function createCheckoutSession( $parameters ) {
		$session = null;
		$parameters = apply_filters( 'fullstripe_checkout_session_parameters', $parameters );
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$parameters = array_merge( $parameters, array( 'validLicense' => $this->validLicense ) );
			$session = $this->remoteRequest(
				'post',
				'/checkout?mode=test&accountId=' . $this->testStripeAcountId . '&api_version=' . $this->userVersion,
				apply_filters( 'fullstripe_checkout_session_parameters', $parameters )
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$parameters = array_merge( $parameters, array( 'validLicense' => $this->validLicense ) );
			$session = $this->remoteRequest(
				'post',
				'/checkout?mode=live&accountId=' . $this->liveStripeAcountId . '&api_version=' . $this->userVersion,
				apply_filters( 'fullstripe_checkout_session_parameters', $parameters )
			);
		} else {
			$session = json_decode( $this->stripe->checkout->sessions->create(
				apply_filters(
					'fullstripe_checkout_session_parameters',
					$parameters
				)
			)->toJSON() );
		}
		return $session;
	}

	/**
	 * @param string $stripeInvoiceId
	 * @throws WPFS_UserFriendlyException
	 */
	public function payInvoiceOutOfBand( $stripeInvoiceId ) {
		$invoice = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoice/pay?mode=test&accountId=' . $this->testStripeAcountId . '&invoiceId=' . $stripeInvoiceId,
				array( 'paid_out_of_band' => true )
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoice/pay?mode=live&accountId=' . $this->liveStripeAcountId . '&invoiceId=' . $stripeInvoiceId,
				array( 'paid_out_of_band' => true )
			);
		} else {
			$invoice = json_decode( $this->stripe->invoices->pay(
				$stripeInvoiceId,
				array( 'paid_out_of_band' => true )
			)->toJSON() );
		}

		return $invoice;
	}

	/**
	 * @param $stripeInvoice
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function finalizeInvoice( $stripeInvoiceId ) {
		$invoice = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'get',
				'/invoice/finalize?mode=test&accountId=' . $this->testStripeAcountId . '&invoiceId=' . $stripeInvoiceId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'get',
				'/invoice/finalize?mode=live&accountId=' . $this->liveStripeAcountId . '&invoiceId=' . $stripeInvoiceId
			);
		} else {
			if ( is_null( $this->stripe ) ) {
				throw new WPFS_UserFriendlyException( 'Stripe client is not initialized' );
			}
			$invoice = json_decode( $this->stripe->invoices->finalizeInvoice( $stripeInvoiceId )->toJSON() );
		}
		return $invoice;
	}

	/**
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function getUpcomingInvoice( $invoiceParams ) {
		$invoice = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoice/list_upcoming?mode=test&accountId=' . $this->testStripeAcountId,
				$invoiceParams
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoice/list_upcoming?mode=live&accountId=' . $this->liveStripeAcountId,
				$invoiceParams
			);
		} else {
			if ( is_null( $this->stripe ) ) {
				throw new WPFS_UserFriendlyException( 'Stripe client is not initialized' );
			}
			$invoice = json_decode( $this->stripe->invoices->upcoming( $invoiceParams )->toJSON() );
		}
		return $invoice;
	}

	/**
	 * Get all line items from an upcoming invoice in Stripe
	 *
	 * @param array $invoiceParams
	 * @throws WPFS_UserFriendlyException
	 */
	public function getUpcomingInvoiceItems( $invoiceParams ) {
		$invoiceItems = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoiceItems = $this->remoteRequest(
				'post',
				'/invoice/item/list_upcoming?mode=test&accountId=' . $this->testStripeAcountId,
				$invoiceParams
			);
			$invoiceItems = $invoiceItems->data;
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoiceItems = $this->remoteRequest(
				'post',
				'/invoice/item/list_upcoming?mode=live&accountId=' . $this->liveStripeAcountId,
				$invoiceParams
			);
			$invoiceItems = $invoiceItems->data;
		} else {
			$invoiceItems = json_decode( $this->stripe->invoices->upcomingLines( $invoiceParams )->toJSON() );
		}
		return $invoiceItems;
	}

	/**
	 * @return \StripeWPFS\StripeClient
	 */
	public function getStripeClient() {
		return $this->stripe;
	}

	/**
	 * @param $priceIds array
	 *
	 * @return array
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrieveProductsByPriceIds( $priceIds ) {
		$products = array();
		foreach ( $priceIds as $priceId ) {
			$price = null;

			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$price = $this->remoteRequest(
					'get',
					'/price?mode=test&accountId=' . $this->testStripeAcountId . '&priceId=' . $priceId
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$price = $this->remoteRequest(
					'get',
					'/price?mode=live&accountId=' . $this->liveStripeAcountId . '&priceId=' . $priceId
				);
			} else {
				$price = json_decode( $this->stripe->prices->retrieve( $priceId, [ 'expand' => [ 'product' ] ] )->toJSON() );
			}
			array_push( $products, $price->product );
		}

		return $products;
	}

	/**
	 * @param $priceIds array
	 *
	 * @return array
	 */
	public function retrieveProductIdsByPriceIds( $priceIds ) {
		$products = $this->retrieveProductsByPriceIds( $priceIds );
		$productIds = array_map(
			function ($o) {
				return $o->id;
			},
			$products
		);

		return $productIds;
	}


	/**
	 * @param $priceId
	 *
	 * @return \StripeWPFS\Price
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrievePriceWithProductExpanded( $priceId ) {
		$price = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$price = $this->remoteRequest(
				'get',
				'/price?mode=test&accountId=' . $this->testStripeAcountId . '&priceId=' . $priceId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$price = $this->remoteRequest(
				'get',
				'/price?mode=live&accountId=' . $this->liveStripeAcountId . '&priceId=' . $priceId
			);
		} else {
			$price = json_decode( $this->stripe->prices->retrieve(
				$priceId,
				array( "expand" => array( "product" ) )
			)->toJSON() );
		}
		return $price;
	}

	/**
	 * @param $taxRateId
	 * @return \StripeWPFS\TaxRate
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function retrieveTaxRate( $taxRateId ) {
		$taxRate = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$taxRate = $this->remoteRequest(
				'get',
				'/tax/rate?mode=test&accountId=' . $this->testStripeAcountId . '&taxRateId=' . $taxRateId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$taxRate = $this->remoteRequest(
				'get',
				'/tax/rate?mode=live&accountId=' . $this->liveStripeAcountId . '&taxRateId=' . $taxRateId
			);
		} else {
			$taxRate = json_decode( $this->stripe->taxRates->retrieve( $taxRateId )->toJSON() );
		}
		return $taxRate;
	}

	/**
	 * @param $stripeCustomerId
	 * @param $taxIdType
	 * @param $taxId
	 * @return \StripeWPFS\TaxId
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function createTaxIdForCustomer( $stripeCustomerId, $taxIdType, $taxId ) {
		$taxId = null;
		$taxBody = array(
			'type' => $taxIdType,
			'value' => $taxId
		);

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$taxId = $this->remoteRequest(
				'post',
				'/tax/id?mode=test&accountId=' . $this->testStripeAcountId . '&customerId=' . $stripeCustomerId,
				$taxBody
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$taxId = $this->remoteRequest(
				'post',
				'/tax/id?mode=live&accountId=' . $this->liveStripeAcountId . '&customerId=' . $stripeCustomerId,
				$taxBody
			);
		} else {
			$taxId = json_decode( $this->stripe->customers->createTaxId( $stripeCustomerId, $taxBody )->toJSON() );
		}
		return $taxId;
	}

	/**
	 * @param $stripeCustomerId
	 * @return \StripeWPFS\Collection
	 * @throws \StripeWPFS\Exception\ApiErrorException
	 * @throws WPFS_UserFriendlyException
	 */
	public function getTaxIdsForCustomer( $stripeCustomerId ) {
		$taxIds = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$taxIds = $this->remoteRequest(
				'get',
				'/tax/id/list?mode=test&accountId=' . $this->testStripeAcountId . '&customerId=' . $stripeCustomerId
			);
			$taxIds = $taxIds->data;
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$taxIds = $this->remoteRequest(
				'get',
				'/tax/id/list?mode=live&accountId=' . $this->liveStripeAcountId . '&customerId=' . $stripeCustomerId
			);
			$taxIds = $taxIds->data;
		} else {
			$taxIds = json_decode( $this->stripe->customers->allTaxIds( $stripeCustomerId, [] )->toJSON() );
		}
		return $taxIds;
	}

	/**
	 * Retrieves the customer's name from Stripe and caches it using a transient.
	 *
	 * @param string $stripeCustomerId The ID of the Stripe customer.
	 * @return string The customer's full name or the customer ID if an error occurs.
	 */
	public function getCustomerName( $stripeCustomerId ) {
		$transient_key = 'wpfs_stripe_customer_name_' . $stripeCustomerId;
		$customer_name = get_transient( $transient_key );

		if ( false === $customer_name ) {
			try {
				$customer = $this->retrieveCustomer( $stripeCustomerId );
				if ( isset( $customer->name ) && ! empty( $customer->name ) ) {
					$customer_name = $customer->name;
					set_transient( $transient_key, $customer_name, WEEK_IN_SECONDS );
				} else {
					$customer_name = $stripeCustomerId;
				}
			} catch ( Exception $e ) {
				$customer_name = $stripeCustomerId;
			}
		}

		return $customer_name;
	}
}
