<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyerFormTemplate;
use Illuminate\Http\Request;

class FormTemplateController extends Controller
{
    /**
     * Tampilkan semua form template
     */
    public function index()
    {
        $templates = BuyerFormTemplate::orderBy('created_at', 'desc')->get();
        return response()->json($templates);
    }

    /**
     * Simpan form template baru
     */
    public function store(Request $request)
    {
        // ✅ PASTIKAN MENGGUNAKAN field_name DAN field_type DI SINI
        $validated = $request->validate([
            'field_name' => 'required|string|max:255',
            'field_type' => 'required|in:text,number,select,radio,file,date',
            'is_active' => 'boolean',
            'options' => 'nullable|array',
        ]);

        if (in_array($validated['field_type'], ['select', 'radio'])) {
            if (empty($validated['options']['choices']) || !is_array($validated['options']['choices'])) {
                return response()->json(['message' => 'Tipe pilihan ganda harus memiliki daftar choices (pilihan).'], 422);
            }
        }

        if ($validated['field_type'] === 'file') {
            $validated['options']['max_size_kb'] = $validated['options']['max_size_kb'] ?? 2048;
            $validated['options']['allowed_extensions'] = $validated['options']['allowed_extensions'] ?? ['.jpg', '.png', '.pdf'];
        }

        $template = BuyerFormTemplate::create($validated);

        return response()->json([
            'message' => 'Template form berhasil dibuat.',
            'data' => $template
        ], 201);
    }

    /**
     * Update form template
     */
    public function update(Request $request, $id)
    {
        $template = BuyerFormTemplate::findOrFail($id);

        // ✅ PASTIKAN JUGA DI UPDATE MENGGUNAKAN field_name DAN field_type
        $validated = $request->validate([
            'field_name' => 'string|max:255',
            'field_type' => 'in:text,number,select,radio,file,date',
            'is_active' => 'boolean',
            'options' => 'nullable|array',
        ]);

        if (isset($validated['field_type']) && in_array($validated['field_type'], ['select', 'radio'])) {
            if (empty($validated['options']['choices']) || !is_array($validated['options']['choices'])) {
                return response()->json(['message' => 'Tipe pilihan ganda harus memiliki daftar choices (pilihan).'], 422);
            }
        }

        $template->update($validated);

        return response()->json([
            'message' => 'Template form berhasil diperbarui.',
            'data' => $template
        ]);
    }

    /**
     * Hapus form template
     */
    public function destroy($id)
    {
        $template = BuyerFormTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'message' => 'Template form berhasil dihapus.'
        ]);
    }
}