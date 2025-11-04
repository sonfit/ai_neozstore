<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'sumary',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function thuTins()
    {
        return $this->belongsToMany(ThuTin::class, 'task_list_thu_tin')->withTimestamps();
    }

    public function tongHopTinhHinhs()
    {
        return $this->belongsToMany(TongHopTinhHinh::class, 'task_list_tong_hop_tinh_hinh')->withTimestamps();
    }
}
