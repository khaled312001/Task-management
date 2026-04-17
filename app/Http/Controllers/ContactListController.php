<?php

namespace App\Http\Controllers;

use App\Models\ContactList;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactListController extends Controller
{
    public function index()
    {
        return response()->json(
            ContactList::where('user_id', Auth::id())->withCount('contacts')->latest()->get()
        );
    }

    public function show(ContactList $contactList)
    {
        $contactList->load('contacts');
        return response()->json($contactList);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);
        $list = ContactList::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description ?? '',
        ]);
        return response()->json($list, 201);
    }

    public function destroy(ContactList $contactList)
    {
        $contactList->delete();
        return response()->json(['success' => true]);
    }

    public function import(Request $request, ContactList $contactList)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls']);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $contacts = [];

        if (in_array($ext, ['csv', 'txt'])) {
            $handle = fopen($file->getRealPath(), 'r');
            $header = null;
            while (($row = fgetcsv($handle)) !== false) {
                if (!$header) {
                    $header = array_map('strtolower', array_map('trim', $row));
                    continue;
                }
                $data = array_combine($header, array_pad($row, count($header), ''));
                $email = $data['email'] ?? $data['e-mail'] ?? $data['بريد'] ?? '';
                $name = $data['name'] ?? $data['الاسم'] ?? $data['full_name'] ?? '';
                $phone = $data['phone'] ?? $data['الهاتف'] ?? $data['mobile'] ?? '';

                if (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                    $contacts[] = [
                        'list_id' => $contactList->id,
                        'email' => trim($email),
                        'name' => trim($name),
                        'phone' => trim($phone),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            fclose($handle);
        }

        if (!empty($contacts)) {
            foreach (array_chunk($contacts, 500) as $chunk) {
                Contact::insert($chunk);
            }
            $contactList->update(['contacts_count' => $contactList->contacts()->count()]);
        }

        return response()->json([
            'success' => true,
            'imported' => count($contacts),
            'message' => 'تم استيراد ' . count($contacts) . ' جهة اتصال',
        ]);
    }

    public function addManual(Request $request, ContactList $contactList)
    {
        $request->validate(['email' => 'required|email']);
        $contact = Contact::create([
            'list_id' => $contactList->id,
            'email' => $request->email,
            'name' => $request->name ?? '',
            'phone' => $request->phone ?? '',
        ]);
        $contactList->update(['contacts_count' => $contactList->contacts()->count()]);
        return response()->json($contact, 201);
    }

    public function removeContact(Contact $contact)
    {
        $listId = $contact->list_id;
        $contact->delete();
        ContactList::where('id', $listId)->update(['contacts_count' => Contact::where('list_id', $listId)->count()]);
        return response()->json(['success' => true]);
    }
}
