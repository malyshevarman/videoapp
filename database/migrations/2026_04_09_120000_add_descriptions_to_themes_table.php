<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->text('contact_description')->nullable()->after('name');
            $table->longText('footer_html')->nullable()->after('contact_description');
        });

        $defaultContactDescription = implode(PHP_EOL, [
            'ООО «БОРИСХОФ 1»',
            'с/п Булатниковское, район 29 км МКАД, уч. 1',
            '+7 495 745-11-11',
        ]);

        $defaultFooterHtml = <<<'HTML'
<p class="bold">
    Уважаемый клиент! Сообщаем Вам, что в Группе Компаний БорисХоф оплата товаров и услуг осуществляется
    исключительно на расчётные счета организации. Пожалуйста, не платите деньги на личные счета
    сторонних лиц! Остерегайтесь мошенников!
</p>
<p class="note__muted">
    Информируем, что отмечаются случаи мошеннических действий,
    когда после передачи автомобиля в автосервис неизвестное лицо от имени сотрудника сервиса звонит
    клиенту, сообщает о выявленной неисправности и под предлогом срочного заказа, необходимых для
    ремонта, запчастей и оперативности их доставки просит перевести денежные средства на его личный
    счет. После перевода денег лицо перестает отвечать на звонки, а автосервис также не может
    ответить за переведенные деньги
</p>
HTML;

        DB::table('themes')
            ->whereNull('contact_description')
            ->update(['contact_description' => $defaultContactDescription]);

        DB::table('themes')
            ->whereNull('footer_html')
            ->update(['footer_html' => $defaultFooterHtml]);
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['contact_description', 'footer_html']);
        });
    }
};
