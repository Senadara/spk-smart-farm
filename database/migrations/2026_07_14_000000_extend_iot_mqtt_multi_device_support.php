<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('iot_connection_config')) {
            Schema::table('iot_connection_config', function (Blueprint $table) {
                if (! Schema::hasColumn('iot_connection_config', 'mqttPort')) {
                    $table->unsignedSmallInteger('mqttPort')->nullable()->after('mqttBrokerUrl');
                }

                if (! Schema::hasColumn('iot_connection_config', 'mqttClientId')) {
                    $table->string('mqttClientId', 150)->nullable()->after('mqttTopic');
                }

                if (! Schema::hasColumn('iot_connection_config', 'mqttUsername')) {
                    $table->string('mqttUsername', 150)->nullable()->after('mqttClientId');
                }

                if (! Schema::hasColumn('iot_connection_config', 'mqttPassword')) {
                    $table->text('mqttPassword')->nullable()->after('mqttUsername');
                }

                if (! Schema::hasColumn('iot_connection_config', 'mqttUseTls')) {
                    $table->boolean('mqttUseTls')->default(false)->after('mqttPassword');
                }

                if (! Schema::hasColumn('iot_connection_config', 'mqttQos')) {
                    $table->unsignedTinyInteger('mqttQos')->default(0)->after('mqttUseTls');
                }

                if (! Schema::hasColumn('iot_connection_config', 'mqttKeepAlive')) {
                    $table->unsignedSmallInteger('mqttKeepAlive')->default(60)->after('mqttQos');
                }
            });
        }

        if (Schema::hasTable('iot_device')) {
            Schema::table('iot_device', function (Blueprint $table) {
                if (! Schema::hasColumn('iot_device', 'mqttTopic')) {
                    $table->string('mqttTopic', 255)->nullable()->after('pollingInterval')->index();
                }

                if (! Schema::hasColumn('iot_device', 'webhookToken')) {
                    $table->string('webhookToken', 255)->nullable()->after('mqttTopic');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('iot_device')) {
            Schema::table('iot_device', function (Blueprint $table) {
                foreach (['webhookToken', 'mqttTopic'] as $column) {
                    if (Schema::hasColumn('iot_device', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('iot_connection_config')) {
            Schema::table('iot_connection_config', function (Blueprint $table) {
                foreach ([
                    'mqttKeepAlive',
                    'mqttQos',
                    'mqttUseTls',
                    'mqttPassword',
                    'mqttUsername',
                    'mqttClientId',
                    'mqttPort',
                ] as $column) {
                    if (Schema::hasColumn('iot_connection_config', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
