<?php

namespace App\Models;

class PublicElection extends Election
{
    protected $connection = 'pgsql';

    protected $table = 'evoting.v_elections';
}
