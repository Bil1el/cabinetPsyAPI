<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable('name', 'description', 'phone', 'email', 'address', 'city', 'wilaya', 'main_image')]
class CabinetSetting extends Model {}
