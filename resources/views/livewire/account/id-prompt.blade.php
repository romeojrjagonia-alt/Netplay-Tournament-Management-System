<?php

use App\Support\Applications\IdDocumentTypes;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * A valid ID is collected once, at sign-up. Accounts that pre-date that (or
 * were added by an admin) are asked for it here, the next time they use the
 * portal, and never again afterwards. Admins are never asked.
 */
new class extends Component {
    use WithFileUploads;

    public bool $show = false;

    public string $documentType = '';

    public string $documentOther = '';

    public $document = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->show = $user !== null
            && ! $user->isAdmin()
            && ! $user->player?->hasIdOnFile();
    }

    public function save(): void
    {
        abort_unless($this->show && auth()->check(), 403);

        $this->validate([
            'documentType' => ['required', Rule::in(array_keys(IdDocumentTypes::TYPES))],
            'documentOther' => ['nullable', 'required_if:documentType,'.IdDocumentTypes::OTHERS, 'string', 'max:100'],
            'document' => ['required', 'image', 'max:4096'],
        ]);

        auth()->user()->ensurePlayer()->update([
            'id_document_type' => $this->documentType,
            'id_document_other' => $this->documentType === IdDocumentTypes::OTHERS ? trim($this->documentOther) : null,
            'id_document_path' => $this->document->store('id-documents', 'public'),
        ]);

        $this->reset('documentType', 'documentOther', 'document');
        $this->show = false;
        $this->dispatch('system-message', title: 'ID saved', message: 'Your valid ID is on file. You won\'t be asked for it again.', variant: 'success');
    }
}; ?>

<div>
    <flux:modal wire:model.self="show" :dismissible="false" :closable="false" x-on:cancel.prevent class="w-full max-w-md dark:bg-white! dark:ring-black/5!">
        <form wire:submit="save" class="flex flex-col gap-4 text-black">
            <div class="flex flex-col gap-1">
                <h2 class="text-lg font-bold">Add your valid ID</h2>
                <p class="text-sm text-black/70">
                    We need a valid ID on file for every account. You only submit it once; it will be reused for all your tournament entries.
                </p>
            </div>

            <x-player.registration-select label="Type of Document" model="documentType" placeholder="Select document type" :live="true" :options="IdDocumentTypes::TYPES" />

            @if ($documentType === IdDocumentTypes::OTHERS)
                <x-player.registration-field label="Specify the type of ID" model="documentOther" placeholder="e.g. Postal ID, Company ID" />
            @endif

            <div x-data="{ fileName: '' }" class="flex flex-col gap-[2px]">
                <label for="idPromptDocument"
                       class="flex h-[80px] cursor-pointer items-center justify-center rounded-[5px] border border-dashed border-[#777] px-[10px] text-center text-sm text-[#838988]">
                    <span x-text="fileName || 'Upload photo of the document'"></span>
                </label>
                <input id="idPromptDocument" type="file" wire:model="document" accept="image/*" class="hidden"
                       x-on:change="fileName = $event.target.files[0]?.name ?? ''">
                @error('document')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="save,document"
                    class="cursor-pointer rounded-[5px] bg-[#0d5750] px-6 py-2.5 text-sm text-white transition hover:bg-[#0b4742] disabled:cursor-not-allowed disabled:opacity-60">
                Save ID
            </button>
        </form>
    </flux:modal>
</div>
