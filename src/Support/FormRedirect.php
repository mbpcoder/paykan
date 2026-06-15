<?php

namespace MbpCoder\Payment\Support;

/**
 * Builds an auto-submitting HTML form for gateways that require a POST-based
 * redirect to their payment page (the framework-agnostic equivalent of
 * larapay's "redirector" blade view).
 */
final class FormRedirect
{
    /**
     * @param string $action Gateway URL to POST to.
     * @param array<string,string|int> $fields Hidden form fields.
     */
    public static function render(string $action, array $fields): string
    {
        $inputs = '';
        foreach ($fields as $name => $value) {
            $inputs .= sprintf(
                '<input type="hidden" name="%s" value="%s">',
                htmlspecialchars((string) $name, ENT_QUOTES),
                htmlspecialchars((string) $value, ENT_QUOTES)
            );
        }

        return sprintf(
            '<!DOCTYPE html><html><body onload="document.forms[0].submit()">'
            . '<form method="post" action="%s">%s'
            . '<noscript><button type="submit">Continue</button></noscript>'
            . '</form></body></html>',
            htmlspecialchars($action, ENT_QUOTES),
            $inputs
        );
    }
}
