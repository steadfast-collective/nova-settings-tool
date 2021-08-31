<?php

namespace Bakerkretzmar\NovaSettingsTool\Http\Controllers;

use Bakerkretzmar\NovaSettingsTool\Events\SettingsChanged;
use Illuminate\Http\Request;

class SettingsToolController
{
    protected $store;

    public function __construct()
    {
        $settingsModel = config('nova-settings-tool.model');
        
        $this->store = $settingsModel::firstOrCreate();
    }

    public function read()
    {
        $values = $this->store->settings;

        $settings = collect(config('nova-settings-tool.settings'));

        $panels = $settings->where('panel', '!=', null)->pluck('panel')->unique()
            ->flatMap(function ($panel) use ($settings) {
                return [$panel => $settings->where('panel', $panel)->pluck('key')->all()];
            })
            ->when($settings->where('panel', null)->isNotEmpty(), function ($collection) use ($settings) {
                return $collection->merge(['_default' => $settings->where('panel', null)->pluck('key')->all()]);
            })
            ->all();

        $settings = $settings->map(function ($setting) use ($values) {
            return array_merge([
                'type' => 'text',
                'label' => ucfirst($setting['key']),
                'value' => $values[$setting['key']] ?? null,
            ], $setting);
        })
            ->keyBy('key')
            ->all();

        return response()->json([
            'title' => config('nova-settings-tool.title', 'Settings'),
            'settings' => $settings,
            'panels' => $panels,
        ]);
    }

    public function write(Request $request)
    {
        $oldSettings = $this->store->settings->toArray();

        foreach ($request->all() as $key => $value) {
            $this->store->settings->put($key, $value);
        }

        $this->store->save();

        event(new SettingsChanged($this->store->settings->toArray(), $oldSettings));

        return response()->json();
    }
}
