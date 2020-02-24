<?php


namespace RandomState\Mint\Tests\Fixtures;


use Illuminate\Database\Eloquent\Model;
use RandomState\Mint\Mint\Billable;

class User extends Model
{
    use Billable;

    protected $guarded = [];
}