<?php

// File generated from our OpenAPI spec

namespace StripeWPFS\Service;

class PriceService extends \StripeWPFS\Service\AbstractService
{
    /**
     * Returns a list of your prices.
     *
     * @param null|array $params
     * @param null|array|\StripeWPFS\Util\RequestOptions $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\Collection<\StripeWPFS\Price>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/prices', $params, $opts);
    }

    /**
     * Creates a new price for an existing product. The price can be recurring or
     * one-time.
     *
     * @param null|array $params
     * @param null|array|\StripeWPFS\Util\RequestOptions $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\Price
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/prices', $params, $opts);
    }

    /**
     * Retrieves the price with the given ID.
     *
     * @param string $id
     * @param null|array $params
     * @param null|array|\StripeWPFS\Util\RequestOptions $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\Price
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/prices/%s', $id), $params, $opts);
    }

    /**
     * Updates the specified price by setting the values of the parameters passed. Any
     * parameters not provided are left unchanged.
     *
     * @param string $id
     * @param null|array $params
     * @param null|array|\StripeWPFS\Util\RequestOptions $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\Price
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/prices/%s', $id), $params, $opts);
    }
}
