<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->deduplicateScammerNames();

        Schema::table('scammers', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('scammers', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }

    private function deduplicateScammerNames(): void
    {
        $groups = [];

        foreach (DB::table('scammers')->orderBy('id')->get(['id', 'name']) as $scammer) {
            $groups[mb_strtolower((string) $scammer->name)][] = $scammer;
        }

        foreach ($groups as $scammers) {
            if (count($scammers) < 2) {
                continue;
            }

            $canonical = array_shift($scammers);

            foreach ($scammers as $duplicate) {
                $this->repointPivot('scammers_organizations', 'organization_id', $duplicate->id, $canonical->id, false);
                $this->repointPivot('scammers_contacts', 'contact_id', $duplicate->id, $canonical->id, true);
                $this->repointPivot('scammers_payment_methods', 'payment_method_id', $duplicate->id, $canonical->id, true);
                $this->repointPivot('scammers_reports', 'report_id', $duplicate->id, $canonical->id, true);

                DB::table('scammers')->where('id', $duplicate->id)->delete();
            }
        }
    }

    private function repointPivot(
        string $table,
        string $relatedColumn,
        int $fromId,
        int $toId,
        bool $softDeletes,
    ): void {
        $links = DB::table($table)->where('scammer_id', $fromId)->get();

        foreach ($links as $link) {
            $existing = DB::table($table)
                ->where('scammer_id', $toId)
                ->where($relatedColumn, $link->{$relatedColumn})
                ->first();

            if ($existing === null) {
                $values = (array) $link;
                unset($values['id']);
                $values['scammer_id'] = $toId;
                DB::table($table)->insert($values);

                continue;
            }

            if ($softDeletes && $existing->deleted_at !== null && $link->deleted_at === null) {
                DB::table($table)->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'updated_at' => $link->updated_at,
                ]);
            }
        }

        DB::table($table)->where('scammer_id', $fromId)->delete();
    }
};
