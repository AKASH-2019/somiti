<?php

namespace App\Http\Requests\Cash;

class RejectHandoverRequest extends ReasonRequest
{
    protected $errorBag = 'reject';

    protected string $ability = 'reject';
}
