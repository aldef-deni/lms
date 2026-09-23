<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Discussion extends Model { protected $guarded=["id"];  public function user(){return $this->belongsTo(User::class);} public function replies(){return $this->hasMany(self::class,'parent_id')->with('user');} }
