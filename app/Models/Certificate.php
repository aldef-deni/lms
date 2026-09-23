<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Certificate extends Model { protected $guarded=["id"]; protected $casts=['completed_at'=>'datetime','issued_at'=>'datetime','revoked_at'=>'datetime']; public function enrollment(){return $this->belongsTo(Enrollment::class);} }
