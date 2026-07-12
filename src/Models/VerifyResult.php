<?php

namespace MbpCoder\Payment\Models;

class VerifyResult
{
    public bool $success;

    public int $ipgStatusCode;

    public string|null $ipgReferenceToken = null;
}
