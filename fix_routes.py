with open('resources/views/welcome.blade.php', 'r') as f:
    content = f.read()

content = content.replace(
    "{{ route('login', ['type' => 'operations']) }}", 
    "{{ route('filament.operations.auth.login') }}"
)
content = content.replace(
    "{{ route('login', ['type' => 'accounting']) }}", 
    "{{ route('filament.accounting.auth.login') }}"
)

with open('resources/views/welcome.blade.php', 'w') as f:
    f.write(content)

print("Fixed routes!")
