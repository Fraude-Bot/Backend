<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->deduplicateOrganizationNames();

        Schema::table('organizations', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }

    private function deduplicateOrganizationNames(): void
    {
        $groups = [];

        foreach (DB::table('organizations')->orderBy('id')->get(['id', 'name']) as $organization) {
            $groups[mb_strtolower((string) $organization->name)][] = $organization;
        }

        foreach ($groups as $organizations) {
            if (count($organizations) < 2) {
                continue;
            }

            $canonical = array_shift($organizations);

            foreach ($organizations as $duplicate) {
                $this->repointPivot('scammers_organizations', 'scammer_id', $duplicate->id, $canonical->id, false);
                $this->repointPivot('organizations_contacts', 'contact_id', $duplicate->id, $canonical->id, true);
                $this->repointPivot('organizations_payment_methods', 'payment_method_id', $duplicate->id, $canonical->id, true);
                $this->repointPivot('organizations_reports', 'report_id', $duplicate->id, $canonical->id, true);

                DB::table('organizations')->where('id', $duplicate->id)->delete();
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
        $links = DB::table($table)->where('organization_id', $fromId)->get();

        foreach ($links as $link) {
            $existing = DB::table($table)
                ->where('organization_id', $toId)
                ->where($relatedColumn, $link->{$relatedColumn})
                ->first();

            if ($existing === null) {
                $values = (array) $link;
                unset($values['id']);
                $values['organization_id'] = $toId;
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

        DB::table($table)->where('organization_id', $fromId)->delete();
    }
};
