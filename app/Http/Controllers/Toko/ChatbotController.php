<?php

namespace App\Http\Controllers\Toko;

use App\Http\Controllers\Controller;
use App\Support\Chatbot\Chatbot;
use App\Support\KontakAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Chatbot storefront (tanpa AI): jawaban dari file teks, bisa diarahkan ke WA/LINE admin. */
class ChatbotController extends Controller
{
    public function mulai(Chatbot $bot): JsonResponse
    {
        return response()->json([
            'teks' => $bot->sapaan(),
            'saran' => $bot->saran(),
            'kontak' => KontakAdmin::tautan(__('Hi, I have a question about :store.', ['store' => config('toko.nama')])),
        ]);
    }

    public function tanya(Request $request, Chatbot $bot): JsonResponse
    {
        $data = $request->validate(['pesan' => ['required', 'string', 'max:500']]);
        $jawab = $bot->jawab($data['pesan']);

        return response()->json([
            'teks' => $jawab['teks'],
            'kontak' => $jawab['admin']
                ? KontakAdmin::tautan(__('Hi, I have a question: :question', ['question' => $data['pesan']]))
                : [],
        ]);
    }
}
