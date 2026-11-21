<?php

namespace App\Http\Requests\Cash;

class DisputeHandoverRequest extends ReasonRequest
{
    protected $errorBag = 'dispute';

    protected string $ability = 'dispute';
}
