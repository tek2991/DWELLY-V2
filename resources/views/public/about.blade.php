<x-public-layout>
    <div class="md:grid md:grid-cols-2 justify-between gap-8 mt-12">
        <div class="bg-darker-3 border-t-8 border-b-8 border-t-piss-yellow border-b-piss-yellow flex items-center md:justify-end py-12" 
            style="background-image:url('{{ url('resources/images/real-estate-bg.svg') }}'); background-repeat: no-repeat;">
            <div class="Left-Space text-secondary text-5xl leading-tight pl-4 md:pr-14 md:text-right">
                What is <br>
                <span class="text-piss-yellow font-GraphikMedium">
                    the mission <br>
                    and vision <br>
                </span>
                of Dwelly
            </div>
        </div>
        <div class="flex items-center p-4 md:p-8 Right-Space xl:w-2/3">
            <div>
                <h1 class="text-5xl text-darker-3 font-GraphikSemibold">
                    Our <br>
                    Vision
                </h1>
                <p class="text-justify text-darker-3 text-lg font-GraphikLight pt-6">
                    Dwelly is an end to end property management firm started
                    with a mission to enable owners to manage and rent out
                    properties in a hassle free way. Dwelly aims to become a
                    virtual property manager for every real estate asset owner.
                </p>
            </div>
        </div>
    </div>

    {{-- Team Section --}}
    <div class="Left-Space pt-20 pb-24 md:flex items-center">
        <h2 class="font-GraphikSemibold text-5xl text-darker-3 p-4 md:mr-36">
            Team <br>
            Dwelly
        </h2>
        <div class="bg-secondary border-l-8 border-l-piss-yellow">
            <div class="Right-Space">
                <div class="p-4 lg:w-3/4">
                    <h3 id="p-name" class="font-GraphikSemibold text-darker-3 text-2xl"></h3>
                    <p id="p-title" class="font-GraphikLight text-darker-3 text-lg"></p>
                    <a id="p-link" href="#" target="_blank" rel="noopener noreferrer" class="flex items-center text-blue-600 mt-1">
                        <svg class="w-5 h-5 mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                            <path fill="#0078d4" d="M24,4C12.954,4,4,12.954,4,24s8.954,20,20,20s20-8.954,20-20S35.046,4,24,4z"/>
                            <path fill="#fff" d="M14,19h4v15h-4V19z M15.988,17h-0.022C14.772,17,14,16.11,14,14.999C14,13.864,14.796,13,16.011,13 c1.217,0,1.966,0.864,1.989,1.999C18,16.11,17.228,17,15.988,17z M35,24.5c0-3.038-2.462-5.5-5.5-5.5 c-1.862,0-3.505,0.928-4.5,2.344V19h-4v15h4v-8c0-1.657,1.343-3,3-3s3,1.343,3,3v8h4C35,34,35,24.921,35,24.5z"/>
                        </svg>
                        <span id="p-link-text"></span>
                    </a>
                    <p id="p-desc" class="my-6 text-sm text-darker-3"></p>
                </div>
                <div class="flex pl-4 pb-4">
                    <div id="p1" onclick="changeProfile('p1')" class="rounded-sm border-b-8 p-2 mx-2 cursor-pointer transition">
                        <img src="{{ url('resources/images/atif.png') }}" alt="Atif" class="h-16 grayscale hover:grayscale-0 ease-in-out duration-300">
                    </div>
                    <div id="p2" onclick="changeProfile('p2')" class="rounded-sm border-b-8 p-2 mx-2 cursor-pointer transition">
                        <img src="{{ url('resources/images/kaushal.png') }}" alt="Kaushal" class="h-16 grayscale hover:grayscale-0 ease-in-out duration-300">
                    </div>
                    <div id="p3" onclick="changeProfile('p3')" class="rounded-sm border-b-8 p-2 mx-2 cursor-pointer transition">
                        <img src="{{ url('resources/images/antarikh.png') }}" alt="Antarikh" class="h-16 grayscale hover:grayscale-0 ease-in-out duration-300">
                    </div>
                </div>
            </div>
        </div>
    </div>

    @section('before-body-end')
        <script>
            const profiles = {
                p1: {
                    name: 'Atif Ayub',
                    title: 'Founding Member',
                    link: 'https://www.linkedin.com/',
                    desc: 'Atif is a Sales professional. He has worked with unicorn start-ups of India like OYO and Zomato. He is an IIT Guwahati graduate with 4 years of work experience.'
                },
                p2: {
                    name: 'Kaushal Choudhury',
                    title: 'Founder',
                    link: 'https://www.linkedin.com/in/kaushal-choudhury/',
                    desc: 'Kaushal is the founder of Dwelly. He is an operations professional and was previously working as the North East India Operations Head for OYO Rooms. He is a 2012 engineering graduate has worked with companies like FLSmidth.'
                },
                p3: {
                    name: 'Antarikh Baruah',
                    title: 'Founding Member, Operations',
                    link: 'https://www.linkedin.com/',
                    desc: 'Antarikh is a sales and operations professional with 6 years of experience. He has previously worked with MNCs like American Express and EXL. He has also dabbled in the startup space of Bangalore before joining Dwelly.'
                }
            };

            function changeProfile(id) {
                document.getElementById('p-name').innerHTML = profiles[id].name;
                document.getElementById('p-title').innerHTML = profiles[id].title;
                document.getElementById('p-link').href = profiles[id].link;
                document.getElementById('p-link-text').innerHTML = profiles[id].name;
                document.getElementById('p-desc').innerHTML = profiles[id].desc;

                document.getElementById(id).classList.add('border-b-piss-yellow', 'bg-darker');
                document.getElementById(id).children[0].classList.remove('grayscale');

                const otherProfiles = Object.keys(profiles).filter((profile) => profile !== id);
                otherProfiles.forEach((profile) => {
                    document.getElementById(profile).classList.remove('border-b-piss-yellow', 'bg-darker');
                    document.getElementById(profile).children[0].classList.add('grayscale');
                });
            }

            changeProfile('p2');
        </script>
    @endsection
</x-public-layout>
