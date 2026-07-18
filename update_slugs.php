<?php

$files = glob("app/Filament/Resources/**/*.php", GLOB_BRACE);
$files = array_merge($files, glob("app/Filament/Resources/**/**/*.php", GLOB_BRACE));

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, "TextInput::make('slug')") !== false) {
        $content = str_replace("TextInput::make('slug')", "TextInput::make('slug')->rule(new \App\Rules\ValidSlug())", $content);
        
        // Also ensure maxLength(255) and unique are there? They mostly are.
        
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
