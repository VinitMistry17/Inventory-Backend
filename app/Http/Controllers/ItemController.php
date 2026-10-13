<?php

namespace App\Http\Controllers;

use App\Contracts\MediaUploader;
use App\Models\Item;
use App\Models\ItemDocument;
use App\Models\ItemPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemController extends Controller
{
    public function __construct(protected MediaUploader $mediaUploader)
    {
    }

    protected function validationRules(bool $isUpdate = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['required', 'exists:categories,id'],
            'purchase_date' => ['nullable', 'date'],
            'has_expiry' => ['required', 'boolean'],
            'expiry_date' => ['required_if:has_expiry,true', 'nullable', 'date'],
            'price_paid' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:150'],
        ];

        if ($isUpdate) {
            $rules['name'] = ['nullable', 'string', 'max:100'];
            $rules['category_id'] = ['nullable', 'exists:categories,id'];
            $rules['has_expiry'] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    public function index(Request $request)
    {
        $query = Item::query()->where('user_id', $request->user()->id);

        if ($request->filled('category')) {
            $query->where('category_id', $request->query('category'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->query('search').'%');
        }

        if ($request->query('sort') === 'expiry_asc') {
            $query->orderByRaw("CASE WHEN has_expiry = 1 THEN 0 ELSE 1 END")
                ->orderBy('expiry_date');
        }

        $items = $query->get([
            'id',
            'name',
            'category_id',
            'purchase_date',
            'has_expiry',
            'expiry_date',
            'price_paid',
            'location',
            'qr_code',
        ]);

        return response()->json($items, 200);
    }

    public function show(Request $request, $id)
    {
        $item = Item::with('category')->where('user_id', $request->user()->id)->find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'category_id' => $item->category_id,
            'category_name' => $item->category->name,
            'purchase_date' => $item->purchase_date,
            'has_expiry' => $item->has_expiry,
            'expiry_date' => $item->expiry_date,
            'price_paid' => $item->price_paid,
            'location' => $item->location,
            'qr_code' => $item->qr_code,
            'photos' => $item->photos->pluck('url')->all(),
            'document_url' => $item->document?->url,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = Item::create([
            'user_id' => $request->user()->id,
            'category_id' => $validator->validated()['category_id'],
            'name' => $validator->validated()['name'],
            'purchase_date' => $validator->validated()['purchase_date'] ?? null,
            'has_expiry' => $validator->validated()['has_expiry'],
            'expiry_date' => $validator->validated()['expiry_date'] ?? null,
            'price_paid' => $validator->validated()['price_paid'] ?? null,
            'location' => $validator->validated()['location'] ?? null,
        ]);

        $item->qr_code = 'tagvault://item/'.$item->id;
        $item->save();

        return response()->json($this->serializeItem($item), 201);
    }

    public function update(Request $request, $id)
    {
        $item = Item::where('user_id', $request->user()->id)->find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->validationRules(true));

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        if (array_key_exists('name', $data)) {
            $item->name = $data['name'];
        }
        if (array_key_exists('category_id', $data)) {
            $item->category_id = $data['category_id'];
        }
        if (array_key_exists('purchase_date', $data)) {
            $item->purchase_date = $data['purchase_date'];
        }
        if (array_key_exists('has_expiry', $data)) {
            $item->has_expiry = $data['has_expiry'];
        }
        if (array_key_exists('expiry_date', $data)) {
            $item->expiry_date = $data['expiry_date'];
        }
        if (array_key_exists('price_paid', $data)) {
            $item->price_paid = $data['price_paid'];
        }
        if (array_key_exists('location', $data)) {
            $item->location = $data['location'];
        }

        $item->save();

        return response()->json($this->serializeItem($item), 200);
    }

    public function destroy(Request $request, $id)
    {
        $item = Item::where('user_id', $request->user()->id)->find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Item deleted successfully'], 200);
    }

    public function uploadPhotos(Request $request, $id)
    {
        $item = Item::where('user_id', $request->user()->id)->find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'photos' => ['required', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $existingCount = $item->photos()->count();
        $incomingCount = count($request->file('photos', []));

        if ($existingCount + $incomingCount > 5) {
            return response()->json([
                'message' => 'You can only upload up to 5 photos per item.',
            ], 422);
        }

        foreach ($request->file('photos', []) as $photo) {
            $result = $this->mediaUploader->uploadFile($photo, 'tagvault/items');

            $item->photos()->create([
                'url' => $result['secure_url'],
                'public_id' => $result['public_id'],
            ]);
        }

        return response()->json([
            'photos' => $item->photos()->pluck('url')->all(),
        ], 200);
    }

    public function deletePhoto(Request $request, $id, $photoId)
    {
        $item = Item::where('user_id', $request->user()->id)->find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $photo = $item->photos()->find($photoId);

        if (! $photo) {
            return response()->json(['message' => 'Photo not found'], 404);
        }

        $this->mediaUploader->delete($photo->public_id);
        $photo->delete();

        return response()->json([
            'photos' => $item->photos()->pluck('url')->all(),
        ], 200);
    }

    public function uploadDocument(Request $request, $id)
    {
        $item = Item::where('user_id', $request->user()->id)->find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'document' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $existingDocument = $item->document()->first();
        if ($existingDocument) {
            $this->mediaUploader->delete($existingDocument->public_id);
            $existingDocument->delete();
        }

        $result = $this->mediaUploader->uploadFile($request->file('document'), 'tagvault/documents');

        $document = $item->document()->create([
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ]);

        return response()->json([
            'document_url' => $document->url,
        ], 200);
    }

    public function deleteDocument(Request $request, $id)
    {
        $item = Item::where('user_id', $request->user()->id)->find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $document = $item->document()->first();
        if (! $document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $this->mediaUploader->delete($document->public_id);
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully'], 200);
    }

    protected function serializeItem(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'category_id' => $item->category_id,
            'purchase_date' => $item->purchase_date,
            'has_expiry' => $item->has_expiry,
            'expiry_date' => $item->expiry_date,
            'price_paid' => $item->price_paid,
            'location' => $item->location,
            'qr_code' => $item->qr_code,
            'photos' => $item->photos->pluck('url')->all(),
            'document_url' => $item->document?->url,
        ];
    }
}
