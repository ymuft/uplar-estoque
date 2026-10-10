<?php

function infinitepay_payment_check_confirms(
    $result,
    int $httpCode,
    string $curlError,
    int $expectedAmount,
    ?int $reportedPaidAmount = null
): bool {
    if (!is_array($result)) {
        return false;
    }

    $gatewayAmount = $result['amount'] ?? null;
    $gatewayPaidAmount = $result['paid_amount'] ?? null;

    return $curlError === ''
        && $httpCode >= 200
        && $httpCode < 300
        && ($result['success'] ?? null) === true
        && ($result['paid'] ?? null) === true
        && is_int($gatewayAmount)
        && $gatewayAmount === $expectedAmount
        && is_int($gatewayPaidAmount)
        && $gatewayPaidAmount >= $expectedAmount
        && ($reportedPaidAmount === null || $gatewayPaidAmount === $reportedPaidAmount);
}
