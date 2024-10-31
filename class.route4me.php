<?php

class Route4Me
{

    private static $orderURL = 'https://api.route4me.com/api.v4/order.php';
    private static $geocoderURL = 'http://api.route4me.com/api/geocoder.php';

    private static $apiKey;

    public static function sendOrder($api_key, array $body)
    {
        $response = wp_remote_post(self::$orderURL . '?' . http_build_query(
            [
                'api_key' => $api_key,
            ]),
            [
                'header' => ['content-type' => 'application/json'],
                'timeout' => 15,
                'httpversion' => '1.1',
                'body' => json_encode($body),
            ]
        );

        return $response;
    }

    public static function orderLookup($api_key, $orderId)
    {
        $response = wp_remote_get(self::$orderURL . '?' . http_build_query(
            [
                'api_key' => $api_key,
                'order_id' => $orderId,
            ]),
            [
                'timeout' => 10,
                'httpversion' => '1.1',
            ]
        );

        return $response;
    }

    public static function isValidKey($api_key)
    {

        $response = wp_remote_get(self::$orderURL . '?' . http_build_query(
            [
                'api_key' => $api_key,
                'limit' => 1,
            ]),
            [
                'timeout' => 15,
                'httpversion' => '1.1',
            ]
        );

        if (is_wp_error($response)) {
            return (object) [
                'status' => false,
                'code' => 500,
                'message' => __('Error validating the API key. Please, try again', 'route4me'),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if (!self::isSuccessCode($statusCode)) {
            return (object) [
                'status' => false,
                'code' => $statusCode,
                'message' => wp_remote_retrieve_body($response),
            ];
        }

        return (object) [
            'status' => true,
        ];
    }

    public static function geocodeAddress($api_key, $address)
    {

        $response = wp_remote_get(
            self::$geocoderURL . '?' . http_build_query(
                [
                    'api_key' => $api_key,
                    'format' => 'json',
                    'addresses' => $address,
                ]),
            [
                'timeout' => 10,
                'httpversion' => '1.1',
            ]
        );

        if (is_wp_error($response)) {
            return new WP_ERROR(500, $response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if (!self::isSuccessCode($statusCode)) {
            return new WP_ERROR($statusCode, wp_remote_retrieve_body($response));
        }

        $responseBody = wp_remote_retrieve_body($response);

        if (strpos($responseBody, 'Invalid') !== false) {
            return new WP_ERROR(500, $responseBody);
        }

        $responseBody = json_decode($responseBody);

        if (empty($responseBody)) {
            return new WP_ERROR(500, "Error! Address couldn't be geocoded");
        }

        $firstDestination = reset($responseBody);

        return (object) [
            'address' => sanitize_text_field($firstDestination->address),
            'lat' => (float) $firstDestination->lat,
            'lng' => (float) $firstDestination->lng,
        ];
    }

    public static function bulkGeocodeOrders($api_key, $orders)
    {
        if (empty($orders)) {
            return;
        }
        $addresses = array_values($orders);
        if (empty($addresses)) {
            return;
        }

        $request = wp_remote_post(
            self::$geocoderURL . '?' . http_build_query(
                [
                    'api_key' => $api_key,
                    'format' => 'json',
                ]),
            [
                'timeout' => 10,
                'httpversion' => '1.1',
                'body' => [
                    'addresses' => implode('\n', $addresses),
                ],
            ]
        );

        $statusCode = wp_remote_retrieve_response_code($request);

        if (is_wp_error($request) || !self::isSuccessCode($statusCode)) {
            return new WP_ERROR($statusCode, self::getErrorMessage($request, __('Error while geocoding addresses.', 'route4me')));
        }

        $ret = [];
        $response = json_decode(wp_remote_retrieve_body($request));
        foreach ($orders as $order => $address) {
            if (in_array($order, array_keys($ret))) {
                continue;
            }
            foreach ($response as $geocoder_addr) {
                if ($address === $geocoder_addr->original) {
                    $ret[$order] = $geocoder_addr;
                }
            }
        }
        return $ret;
    }

    private static function isSuccessCode($code)
    {
        if ($code >= 200 && $code < 300) {
            return true;
        }
        return false;
    }

    private static function getErrorMessage($response, $default = '')
    {
        $response = json_decode(wp_remote_retrieve_body($response));
        if (!empty($response->errors) && is_array($response->errors)) {
            return implode(', ', $response->errors);
        }
        return $default;
    }
}
