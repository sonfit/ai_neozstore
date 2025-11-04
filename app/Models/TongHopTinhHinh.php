<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TongHopTinhHinh extends Model
{
    protected $table = 'tong_hop_tinh_hinhs';

    protected $fillable = [
        'link',
        'contents_text',
        'id_muctieu',
        'pic',
        'sumary',
        'phanloai',
        'id_user',
        'id_bot',
        'diem',
        'time',
    ];

    protected $casts = [
        'pic' => 'array',
    ];

    protected static function booted()
    {
        static::deleting(function ($record) {
            if ($record->pic) {
                Storage::disk('public')->delete($record->pic);
            }
        });

        static::updating(function ($record) {
            if ($record->isDirty('pic')) {
                // Normalize old pictures to array of strings
                $oldPic = $record->getOriginal('pic');
                if (is_string($oldPic)) {
                    $decoded = json_decode($oldPic, true);
                    $oldPic = json_last_error() === JSON_ERROR_NONE ? $decoded : [$oldPic];
                }
                if ($oldPic === null) {
                    $oldPic = [];
                }
                if (!is_array($oldPic)) {
                    $oldPic = [$oldPic];
                }

                // Normalize new pictures to array of strings
                $newPic = $record->pic;
                if (is_string($newPic)) {
                    $decoded = json_decode($newPic, true);
                    $newPic = json_last_error() === JSON_ERROR_NONE ? $decoded : [$newPic];
                }
                if ($newPic === null) {
                    $newPic = [];
                }
                if (!is_array($newPic)) {
                    $newPic = [$newPic];
                }

                $toDelete = array_diff($oldPic, $newPic);
                if (!empty($toDelete)) {
                    Storage::disk('public')->delete($toDelete);
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function mucTieu()
    {
        return $this->belongsTo(MucTieu::class, 'id_muctieu');
    }

    public function bot()
    {
        return $this->belongsTo(Bot::class, 'id_bot');
    }

    public function taskLists()
    {
        return $this->belongsToMany(TaskList::class, 'task_list_tong_hop_tinh_hinh')->withTimestamps();
    }
}
