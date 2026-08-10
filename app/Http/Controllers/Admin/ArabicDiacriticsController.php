<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ArabicDiacriticizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArabicDiacriticsController extends Controller
{
    public function diacritize(Request $request, ArabicDiacriticizer $diacriticizer): JsonResponse
    {
        $text = trim($request->string('text'));

        if (blank($text)) {
            return response()->json(['error' => 'No text provided'], 422);
        }

        $diacritized = $diacriticizer->diacritize($text);

        if ($diacritized === null) {
            return response()->json(
                ['error' => 'Could not add vowels. The service may be unavailable. Try again or add them manually.'],
                503
            );
        }

        return response()->json(['text' => $diacritized]);
    }
}
