<?php

namespace Fleetbase\Http\Controllers\Internal\v1;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Support\Http;
use Fleetbase\Types\Country;
use Fleetbase\Types\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class LookupController extends Controller
{
    /**
     * Query and search font awesome icons.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function fontAwesomeIcons(Request $request)
    {
        $query = $request->input('query');
        $id = $request->input('id');
        $prefix = $request->input('prefix');
        $limit = $request->input('limit');

        $content = file_get_contents('https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/metadata/icons.json');
        $json = json_decode($content);
        $icons = [];

        $count = 0;

        if ($query) {
            $query = strtolower($query);
        }

        foreach ($json as $icon => $value) {
            $searchTerms = [...$value->search->terms, strtolower($value->label)];

            if (
                $query && collect($searchTerms)->every(
                    function ($term) use ($query) {
                        return !Str::contains($term, $query);
                    }
                )
            ) {
                continue;
            }

            if ($limit && $count >= $limit) {
                break;
            }

            if ($id && $id !== $icon) {
                continue;
            }

            foreach ($value->styles as $style) {
                $iconPrefix = 'fa' . substr($style, 0, 1);

                if ($prefix && $prefix !== $iconPrefix) {
                    continue;
                }

                $icons[] = [
                    'prefix' => $iconPrefix,
                    'label' => $value->label,
                    'id' => $icon
                ];
            }

            $count++;
        }

        return $icons;
    }

    /**
     * Request IP lookup on user client.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function whois(Request $request)
    {
        try {
            $info = Http::lookupIp($request);
        } catch (Exception $e) {
            return response()->error($e->getMessage());
        }

        return response()->json($info);
    }

    /**
     * Get all countries with search enabled.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function currencies(Request $request)
    {
        $query = strtolower($request->input('query'));

        $currencies = Currency::filter(
            function ($currency) use ($query) {
                if ($query) {
                    return Str::contains(strtolower($currency->getCode()), $query) || Str::contains(strtolower($currency->getTitle()), $query);
                }

                return true;
            }
        );

        return response()->json($currencies);
    }

    /**
     * Get all countries with search enabled.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function countries(Request $request)
    {
        $query = strtolower($request->input('query'));
        $simple = $request->boolean('simple');
        $columns = $request->input('columns', []);

        $countries = Country::search($query);

        if ($columns) {
            $countries = $countries->map(
                function ($country) use ($columns) {
                    return $country->only($columns);
                }
            );
        }

        if ($simple) {
            $countries = $countries->map(
                function ($country) {
                    return $country->simple();
                }
            );
        }

        return response()->json($countries);
    }

    /**
     * Lookup a country by it's country or currency code.
     *
     * @param string $code
     * @return \Illuminate\Http\Response
     */
    public function country($code, Request $request)
    {
        $simple = $request->boolean('simple', true);
        $country = Country::search($code)->first();

        if ($simple && $country) {
            $country = $country->simple();
        }

        return response()->json($country);
    }
}