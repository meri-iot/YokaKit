<?php

use Diglactic\Breadcrumbs\Breadcrumbs;

// ホーム
Breadcrumbs::for('home', function ($trail) {
    $trail->push(__('yokakit.home'), route('home'));
});

// アンドン設定
Breadcrumbs::for('andon.config', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.target_config', ['target' => __('yokakit.andon')]), route('andon.config'));
});

// 品番切り替え
Breadcrumbs::for('switch.index', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.switch_part_number') . ' & ' . __('yokakit.switch_producer'), route('switch.index'));
});

// アバウト
Breadcrumbs::for('about', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.about'), route('about'));
});

// プロフィール
Breadcrumbs::for('user.show', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.profile'), route('user.show'));
});

// プロフィール編集
Breadcrumbs::for('user.edit', function ($trail) {
    $trail->parent('user.show');
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.profile')]));
});

// ユーザー一覧
Breadcrumbs::for('user.index', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.target_list', ['target' => __('yokakit.user')]), route('user.index'));
});

// ユーザー作成
Breadcrumbs::for('user.create', function ($trail) {
    $trail->parent('user.index');
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.user')]));
});

// パスワード編集
Breadcrumbs::for('user.password', function ($trail) {
    $trail->parent('user.show');
    $trail->push(__('yokakit.change_password'));
});

// 工程一覧
Breadcrumbs::for('process.index', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.target_list', ['target' => __('yokakit.process')]), route('process.index'));
});

// 工程追加
Breadcrumbs::for('process.create', function ($trail) {
    $trail->parent('process.index');
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.process')]));
});

// 工程詳細
Breadcrumbs::for('process.show', function ($trail, $process) {
    $trail->parent('process.index');
    $trail->push($process->process_name, route('process.show', ['process' => $process]));
});

// 工程編集
Breadcrumbs::for('process.edit', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.process')]));
});

// 生産履歴品番切り替え
Breadcrumbs::for('production.create', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.switch_part_number'));
});

// 生産履歴
Breadcrumbs::for('production.index', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.production_history'), route('production.index', ['process' => $process]));
});

// 履歴詳細
Breadcrumbs::for('production.show', function ($trail, $process) {
    $trail->parent('production.index', $process);
    $trail->push(__('yokakit.display_chart'));
});

// 計画停止時間一覧
Breadcrumbs::for('planned-outage.index', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.target_list', ['target' => __('yokakit.planned_outage')]), route('planned-outage.index'));
});

// 計画停止時間追加
Breadcrumbs::for('planned-outage.create', function ($trail) {
    $trail->parent('planned-outage.index');
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.planned_outage')]));
});

// 計画停止時間編集
Breadcrumbs::for('planned-outage.edit', function ($trail) {
    $trail->parent('planned-outage.index');
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.planned_outage')]));
});

// 品番一覧
Breadcrumbs::for('part-number.index', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.target_list', ['target' => __('yokakit.part_number')]), route('part-number.index'));
});

// 品番追加
Breadcrumbs::for('part-number.create', function ($trail) {
    $trail->parent('part-number.index');
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.part_number')]));
});

// 品番編集
Breadcrumbs::for('part-number.edit', function ($trail) {
    $trail->parent('part-number.index');
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.part_number')]));
});

// サイクルタイム追加
Breadcrumbs::for('cycle-time.create', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.cycle_time')]));
});

// サイクルタイム編集
Breadcrumbs::for('cycle-time.edit', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.cycle_time')]));
});

// 作業者一覧
Breadcrumbs::for('worker.index', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.target_list', ['target' => __('yokakit.worker')]), route('worker.index'));
});

// 作業者追加
Breadcrumbs::for('worker.create', function ($trail) {
    $trail->parent('worker.index');
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.worker')]));
});

// 作業者編集
Breadcrumbs::for('worker.edit', function ($trail) {
    $trail->parent('worker.index');
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.worker')]));
});

// 工程計画停止時間追加
Breadcrumbs::for('process.planned-outage.create', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.process_planned_outage')]));
});

// ラズベリーパイ一覧
Breadcrumbs::for('raspberry-pi.index', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.target_list', ['target' => __('yokakit.raspberry_pi')]), route('raspberry-pi.index'));
});

// ラズベリーパイ追加
Breadcrumbs::for('raspberry-pi.create', function ($trail) {
    $trail->parent('raspberry-pi.index');
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.raspberry_pi')]));
});

// ラズベリーパイ編集
Breadcrumbs::for('raspberry-pi.edit', function ($trail) {
    $trail->parent('raspberry-pi.index');
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.raspberry_pi')]));
});

// アラーム追加
Breadcrumbs::for('alarm.create', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.alarm')]));
});

// アラーム編集
Breadcrumbs::for('alarm.edit', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.alarm')]));
});

// ON-OFFメッセージ一覧
Breadcrumbs::for('onoff.index', function ($trail, $process) {
    $trail->parent('process.index', $process);
    $trail->push($process->process_name . '：' . __('yokakit.notification'));
});

// ON-OFF追加
Breadcrumbs::for('onoff.create', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_add', ['target' =>  __('yokakit.notification')]));
});

// ON-OFF編集
Breadcrumbs::for('onoff.edit', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.notification')]));
});

// ライン追加
Breadcrumbs::for('line.create', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.line')]));
});

// ライン編集
Breadcrumbs::for('line.edit', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.line')]));
});

// ライン並べ替え
Breadcrumbs::for('line.sorting', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_sort', ['target' => __('yokakit.line')]));
});

// ガントチャート追加
Breadcrumbs::for('gantt-chart.create', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_add', ['target' => __('yokakit.gantt_chart')]));
});

// ガントチャート編集
Breadcrumbs::for('gantt-chart.edit', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_edit', ['target' => __('yokakit.gantt_chart')]));
});

// ガントチャート並べ替え
Breadcrumbs::for('gantt-chart.sorting', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push(__('yokakit.target_sort', ['target' => __('yokakit.gantt_chart')]));
});

// ガントチャート表示(工程別)
Breadcrumbs::for('gantt-chart.index', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push($process->process_name . '：' . __('yokakit.gantt_chart'));
});

// ガントチャート履歴
Breadcrumbs::for('gantt-chart.history', function ($trail, $process) {
    $trail->parent('process.show', $process);
    $trail->push($process->process_name . '：' . __('yokakit.gantt_chart_history'));
});

// ガントチャート
Breadcrumbs::for('gantt.index', function ($trail) {
    $trail->parent('home');
    $trail->push(__('yokakit.gantt_chart'));
});
