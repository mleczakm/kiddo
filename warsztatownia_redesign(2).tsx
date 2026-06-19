import React, { useState, useRef, useEffect } from 'react';
import { 
  Calendar, Users, LayoutDashboard, ChevronLeft, CheckCircle, 
  CreditCard, Menu, Clock, Ticket, User, MapPin, Search, 
  Settings, Bell, Edit, Trash2, Plus, Filter, MessageSquare, UserCheck, BookOpen,
  X, RefreshCw, Ban, ShieldAlert, FileText, DollarSign, Lock, Unlock, Repeat, Check,
  AlertTriangle, Mail, Phone, ExternalLink, CalendarDays, Eye, EyeOff, RotateCcw, Building, FileCode, Send, LogOut, Sliders,
  Copy, ArrowDownToLine, MoreVertical
} from 'lucide-react';

// --- MOCK DATA ---
const CURRENT_DATE_STRING = '15.06.2026'; // Symulacja dzisiejszej daty do obliczeń przeszłość/przyszłość

const WORKSHOPS = [
  { id: 1, title: 'Sensoplastyka dla maluchów', age: '0-3 lat', price: 55, category: 'Sensoryka', img: 'bg-orange-100', color: 'text-orange-600', badge: 'bg-orange-200', description: 'Zajęcia ogólnorozwojowe z wykorzystaniem jadalnych materiałów.', instructor: 'Marta W.', repeatability: 'Co tydzień w środę' },
  { id: 2, title: 'Błotna Kuchnia', age: '3-6 lat', price: 60, category: 'Brudna zabawa', img: 'bg-green-100', color: 'text-green-600', badge: 'bg-green-200', description: 'Bawimy się ziemią, wodą, liśćmi. Konstruujemy potrawy w ogrodzie.', instructor: 'Anna K.', repeatability: 'Co tydzień we wtorek' },
];

const WORKSHOP_INSTANCES = [
  // Przeszłe
  { id: 'W1-P1', workshopId: 1, date: '01.06.2026', time: '10:00 - 11:30', totalSpots: 10, spotsTaken: 10, status: 'completed' },
  { id: 'W1-P2', workshopId: 1, date: '08.06.2026', time: '10:00 - 11:30', totalSpots: 10, spotsTaken: 9, status: 'completed' },
  // Aktualne / Przyszłe
  { id: 'W1-I1', workshopId: 1, date: '15.06.2026', time: '10:00 - 11:30', totalSpots: 10, spotsTaken: 8, status: 'active' },
  { id: 'W1-I2', workshopId: 1, date: '22.06.2026', time: '10:00 - 11:30', totalSpots: 10, spotsTaken: 10, status: 'active' }, // Pełne zajęcia
  { id: 'W2-I1', workshopId: 2, date: '16.06.2026', time: '16:30 - 18:00', totalSpots: 8, spotsTaken: 2, status: 'active' },
];

const RESERVATIONS_MOCK = [
  { id: 'RES-001', instanceId: 'W1-I1', clientId: 'C-001', child: 'Antosia', age: '2 lata', parentName: 'Magdalena Kowalska', email: 'magda@example.com', phone: '123456789', ticket: 'Jednorazowe', status: 'paid', amount: 55, created: '10.06.2026' },
  { id: 'RES-002', instanceId: 'W1-I1', clientId: 'C-002', child: 'Jaś', age: '3 lata', parentName: 'Anna Wiśniewska', email: 'anna@example.com', phone: '987654321', ticket: 'Karnet 4-wejść (1/4)', status: 'paid', amount: 0, created: '11.06.2026' },
  // Oczekuje na płatność (Zgodnie z wymaganiem: rezerwacja blokuje miejsce, widnieje jako aktywna)
  { id: 'RES-003', instanceId: 'W1-I1', clientId: 'C-003', child: 'Zosia', age: '1.5 roku', parentName: 'Piotr Nowak', email: 'piotr@example.com', phone: '555666777', ticket: 'Jednorazowe', status: 'pending', amount: 55, created: '14.06.2026', risk: 'expires_soon' },
  { id: 'RES-004', instanceId: 'W2-I1', clientId: 'C-004', child: 'Krzyś', age: '2 lata', parentName: 'Ewa Zielińska', email: 'ewa@example.com', phone: '111222333', ticket: 'Jednorazowe', status: 'transferred', amount: 60, note: 'Przeniesione z 09.06', created: '01.06.2026' },
  { id: 'RES-005', instanceId: 'W1-I1', clientId: 'C-005', child: 'Maja', age: '2 lata', parentName: 'Kamil K.', email: 'kamil@example.com', phone: '000000000', ticket: 'Jednorazowe', status: 'cancelled', amount: 55, created: '05.06.2026' },
  // PROAKTYWNE ROZWIĄZANIE: Lista rezerwowa (nie blokuje miejsca)
  { id: 'RES-007', instanceId: 'W1-I2', clientId: 'C-006', child: 'Olaf', age: '3 lata', parentName: 'Julia M.', email: 'julia@example.com', phone: '111111111', ticket: 'Jednorazowe', status: 'waitlist', amount: 0, created: '12.06.2026' }
];

const MOCK_CLIENTS = [
  { id: 'C-001', name: 'Magdalena Kowalska', email: 'magda@example.com', phone: '+48 123 456 789', registered: '10.01.2024', status: 'active', children: [{name: 'Antosia', age: '2l'}, {name: 'Igor', age: '5l'}] },
  { id: 'C-002', name: 'Anna Wiśniewska', email: 'anna@example.com', phone: '+48 987 654 321', registered: '15.03.2024', status: 'active', children: [{name: 'Jaś', age: '3l'}] },
  { id: 'C-003', name: 'Piotr Nowak', email: 'piotr@example.com', phone: '+48 555 666 777', registered: '01.06.2024', status: 'active', children: [{name: 'Zosia', age: '1.5r'}] },
  { id: 'C-004', name: 'Ewa Zielińska', email: 'ewa@example.com', phone: '+48 111 222 333', registered: '01.06.2024', status: 'active', children: [{name: 'Krzyś', age: '2l'}] },
  { id: 'C-006', name: 'Julia M.', email: 'julia@example.com', phone: '+48 111 111 111', registered: '01.05.2024', status: 'active', children: [{name: 'Olaf', age: '3l'}] },
];

const PLATFORM_BILLING = {
  lastMonth: { month: 'Maj 2026', amount: 145.50, status: 'unpaid' },
  currentMonth: { month: 'Czerwiec 2026', estimated: 85.20, totalMatched: 4260.00 }
};

const MOCK_TRANSFERS = [
  { id: 'TR-1029', date: '15.06.2026', title: 'Kiddo - Sensoplastyka Zosia', amount: '55.00', sender: 'Piotr Nowak', status: 'unmatched', resId: null },
  { id: 'TR-1031', date: '16.06.2026', title: 'Opłata za zajęcia 16 czerwiec', amount: '60.00', sender: 'Ewa Zielińska', status: 'matched', resId: 'RES-004' },
];

const DynamicLink = ({ type, id, label, navigate, className = "", noWrap = false }) => {
  const handleClick = (e) => {
    e.stopPropagation();
    if (type === 'client') navigate({ view: 'client_detail', id });
    if (type === 'workshop') navigate({ view: 'workshop_detail', id });
    if (type === 'date') navigate({ view: 'daily_schedule', date: id });
  };
  
  return (
    <button type="button" onClick={handleClick} className={`hover:text-indigo-600 hover:underline transition-colors font-bold text-left focus:outline-none ${className}`}>
      {label}
    </button>
  );
};

// ==========================================
// MAIN APP COMPONENT
// ==========================================
export default function WarsztatowniaApp() {
  const [viewMode, setViewMode] = useState('admin');

  return (
    <div className="flex flex-col min-h-screen bg-[#faf8f5] font-sans">
      <div className="bg-slate-800 text-white p-3 flex flex-wrap justify-center items-center gap-4 text-sm z-50 shadow-md">
        <span className="opacity-70 font-medium hidden sm:inline">Przełącznik makiety:</span>
        <div className="flex gap-2">
          <button onClick={() => setViewMode('client')} className={`px-4 py-2 rounded-lg transition-colors font-medium ${viewMode === 'client' ? 'bg-teal-500 text-white' : 'bg-slate-700 hover:bg-slate-600'}`}>
            📱 Klient (Mobilka)
          </button>
          <button onClick={() => setViewMode('admin')} className={`px-4 py-2 rounded-lg transition-colors font-medium ${viewMode === 'admin' ? 'bg-[#eb4a54] text-white' : 'bg-slate-700 hover:bg-slate-600'}`}>
            💻 Panel Admina (CRM)
          </button>
        </div>
      </div>
      <div className="flex-grow flex justify-center w-full">
        {viewMode === 'client' ? <ClientApp /> : <AdminApp />}
      </div>
    </div>
  );
}

function ClientApp() {
  return (
    <div className="w-full max-w-md bg-white min-h-[850px] shadow-2xl sm:my-8 sm:rounded-[2.5rem] relative overflow-hidden border-8 border-slate-800/10 flex flex-col items-center justify-center p-8 text-center">
        <h2 className="text-2xl font-black text-[#eb4a54] mb-4">Widok Klienta</h2>
        <p className="text-gray-500 mb-4">Makieta mobilna została zaprezentowana w poprzednich krokach.</p>
        <p className="text-gray-500 text-sm">Przełącz na <b>Panel Admina</b> używając górnego paska, aby ocenić kompletną architekturę CRM, dynamiczne linki, listę rezerwową, odświeżony widok warsztatów (Oś czasu) oraz zaawansowane Ustawienia.</p>
    </div>
  )
}

function AdminApp() {
  const [navState, setNavState] = useState({ view: 'dashboard', id: null, date: null });
  const [isProfileMenuOpen, setIsProfileMenuOpen] = useState(false);
  const profileMenuRef = useRef(null);

  useEffect(() => {
    function handleClickOutside(event) {
      if (profileMenuRef.current && !profileMenuRef.current.contains(event.target)) {
        setIsProfileMenuOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  return (
    <div className="flex w-full min-h-screen bg-[#faf8f5] text-slate-800">
      {/* Sidebar */}
      <aside className="w-64 bg-slate-900 text-slate-300 flex flex-col hidden md:flex shrink-0 z-20 shadow-xl">
        <div className="p-6">
          <h1 className="text-2xl font-black text-[#faf8f5] tracking-tight">Kiddo<span className="text-[#eb4a54]">.</span></h1>
          <p className="text-xs text-slate-500 uppercase tracking-wider font-bold mt-1">System Zarządzania</p>
        </div>
        
        <nav className="flex-1 px-4 space-y-1">
          <SidebarItem icon={<LayoutDashboard size={18}/>} label="Pulpit" active={navState.view === 'dashboard'} onClick={() => setNavState({view: 'dashboard'})} />
          <SidebarItem icon={<BookOpen size={18}/>} label="Zajęcia (Szablony)" active={navState.view === 'workshops' || navState.view === 'workshop_detail'} onClick={() => setNavState({view: 'workshops'})} />
          <SidebarItem icon={<Calendar size={18}/>} label="Harmonogram" active={navState.view === 'daily_schedule'} onClick={() => setNavState({view: 'daily_schedule', date: CURRENT_DATE_STRING})} />
          <SidebarItem icon={<Ticket size={18}/>} label="Wszystkie Rezerwacje" active={navState.view === 'reservations'} onClick={() => setNavState({view: 'reservations'})} />
          <SidebarItem icon={<DollarSign size={18}/>} label="Płatności / Przelewy" active={navState.view === 'transfers'} badge="1" onClick={() => setNavState({view: 'transfers'})} />
          <SidebarItem icon={<Users size={18}/>} label="Użytkownicy" active={navState.view.includes('client')} onClick={() => setNavState({view: 'clients'})} />
        </nav>
        
        <div className="p-4 border-t border-slate-800">
          <SidebarItem icon={<Settings size={18}/>} label="Ustawienia" active={navState.view === 'settings'} onClick={() => setNavState({view: 'settings'})} />
        </div>
      </aside>

      <main className="flex-1 flex flex-col overflow-hidden h-screen relative">
        <header className="bg-white h-16 border-b border-[#e5d8c3] flex items-center justify-between px-6 shrink-0 z-30 shadow-sm">
          <div className="font-bold text-lg text-slate-800 flex items-center gap-2">
            {(navState.view === 'workshop_detail' || navState.view === 'client_detail' || navState.view === 'daily_schedule') && 
              <button onClick={() => setNavState({view: navState.view === 'workshop_detail' ? 'workshops' : 'dashboard'})} className="p-1.5 mr-2 bg-slate-100 rounded-lg hover:bg-slate-200"><ChevronLeft size={18}/></button>
            }
            {navState.view === 'dashboard' && 'Pulpit'}
            {navState.view === 'workshops' && 'Zarządzanie Zajęciami (Szablony)'}
            {navState.view === 'workshop_detail' && 'Szczegóły Zajęć i Wystąpienia'}
            {navState.view === 'daily_schedule' && `Harmonogram Dnia: ${navState.date || ''}`}
            {navState.view === 'reservations' && 'Wszystkie Rezerwacje'}
            {navState.view === 'clients' && 'Użytkownicy (CRM)'}
            {navState.view === 'client_detail' && 'Karta Klienta'}
            {navState.view === 'transfers' && 'Płatności i Przelewy bankowe'}
            {navState.view === 'settings' && 'Ustawienia i Raportowanie'}
          </div>
          <div className="flex items-center gap-4">
             {/* Profile Dropdown */}
            <div className="relative" ref={profileMenuRef}>
              <div 
                className="w-10 h-10 bg-[#eb4a54] rounded-full flex items-center justify-center text-white font-bold text-lg cursor-pointer hover:bg-red-600 transition shadow-sm"
                onClick={() => setIsProfileMenuOpen(!isProfileMenuOpen)}
              >
                M
              </div>
              
              {isProfileMenuOpen && (
                <div className="absolute right-0 mt-2 w-72 bg-white rounded-2xl shadow-[0_10px_40px_rgba(0,0,0,0.1)] border border-slate-100 overflow-hidden animate-in fade-in slide-in-from-top-2">
                  <div className="p-4 border-b border-slate-100 bg-slate-50 flex items-start gap-3">
                     <div className="w-10 h-10 bg-[#eb4a54] rounded-full flex items-center justify-center text-white font-bold shrink-0">M</div>
                     <div>
                        <div className="font-bold text-slate-800 text-sm">Michał (Admin)</div>
                        <div className="text-xs text-slate-500">do@mleczki.pl</div>
                     </div>
                  </div>
                  <div className="p-2 space-y-0.5">
                     <DropdownItem icon={<Bell size={16}/>} label="Powiadomienia" badge="Brak nowych" onClick={() => {}} />
                     <div className="h-px bg-slate-100 my-1"></div>
                     <DropdownItem icon={<LayoutDashboard size={16}/>} label="Panel administracyjny" active onClick={() => {}} />
                     <DropdownItem icon={<Eye size={16}/>} label="Podszyj się (Demo)" onClick={() => {}} />
                     <div className="h-px bg-slate-100 my-1"></div>
                     <DropdownItem icon={<LogOut size={16} className="text-red-500"/>} label="Wyloguj się" textColor="text-red-600" onClick={() => {}} />
                  </div>
                </div>
              )}
            </div>
          </div>
        </header>

        <div className="flex-1 overflow-auto bg-[#faf8f5] p-6 custom-scrollbar relative">
          <div className="max-w-7xl mx-auto space-y-6">
            
            {/* ALERT BILLINGOWY PLATFORMY */}
            {PLATFORM_BILLING.lastMonth.status === 'unpaid' && navState.view === 'dashboard' && (
              <div className="bg-red-50 border border-red-200 rounded-2xl p-4 shadow-sm flex items-start gap-4 animate-in slide-in-from-top-4">
                <div className="bg-red-100 p-2 rounded-full text-red-600 shrink-0"><AlertTriangle size={24}/></div>
                <div className="flex-1">
                  <h3 className="font-bold text-red-800 text-lg">Zaległa opłata systemowa (2% od przelewów) za ubiegły miesiąc ({PLATFORM_BILLING.lastMonth.month})</h3>
                  <p className="text-red-700 text-sm mt-1">Kwota do zapłaty: <b>{PLATFORM_BILLING.lastMonth.amount} zł</b>.</p>
                </div>
                <button className="bg-[#eb4a54] hover:bg-red-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm transition whitespace-nowrap">Opłać teraz</button>
              </div>
            )}

            {navState.view === 'dashboard' && <AdminDashboard navigate={setNavState} />}
            {navState.view === 'workshops' && <AdminWorkshops navigate={setNavState} />}
            {navState.view === 'workshop_detail' && <AdminWorkshopDetails id={navState.id} navigate={setNavState} />}
            {navState.view === 'daily_schedule' && <AdminDailySchedule date={navState.date} navigate={setNavState} />}
            {navState.view === 'reservations' && <AdminReservations navigate={setNavState} />}
            {navState.view === 'clients' && <AdminClients navigate={setNavState} />}
            {navState.view === 'client_detail' && <AdminClientDetails id={navState.id} navigate={setNavState} />}
            {navState.view === 'transfers' && <AdminTransfers navigate={setNavState} />}
            {navState.view === 'settings' && <AdminSettings />}
          </div>
        </div>
      </main>
    </div>
  );
}

function SidebarItem({ icon, label, active, onClick, badge }) {
  return (
    <button onClick={onClick} className={`w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors text-sm font-medium ${active ? 'bg-white/10 text-white' : 'hover:bg-slate-800 hover:text-white'}`}>
      <div className="flex items-center gap-3">{icon}<span>{label}</span></div>
      {badge && <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${active ? 'bg-[#eb4a54] text-white' : 'bg-[#eb4a54]/20 text-[#eb4a54]'}`}>{badge}</span>}
    </button>
  );
}

function DropdownItem({ icon, label, onClick, active, badge, textColor = "text-slate-600" }) {
   return (
      <button onClick={onClick} className={`w-full flex items-center justify-between px-3 py-2 rounded-lg transition-colors text-sm font-medium ${active ? 'bg-indigo-50 text-indigo-700' : `hover:bg-slate-50 ${textColor}`}`}>
         <div className="flex items-center gap-3">{icon}<span>{label}</span></div>
         {badge && <span className="text-[10px] text-slate-400 font-normal">{badge}</span>}
      </button>
   )
}

function AdminDashboard({ navigate }) {
  const problemReservations = RESERVATIONS_MOCK.filter(r => r.status === 'pending' || r.status === 'expired');
  const activeInstances = WORKSHOP_INSTANCES.filter(i => i.status === 'active');

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        
        {/* Kolumna 1 i 2: Najbliższe zajęcia i Rezerwacje */}
        <div className="xl:col-span-2 bg-white rounded-3xl border border-[#e5d8c3] shadow-sm overflow-hidden flex flex-col">
          <div className="p-5 border-b border-[#e5d8c3] flex justify-between items-center bg-[#fdf8ee]">
            <h3 className="font-bold text-[#784421] flex items-center gap-2"><Users size={18}/> Przegląd Najbliższych Zajęć i Rezerwacji</h3>
            <div className="flex gap-2 items-center">
               <button className="p-1.5 border border-[#e5d8c3] bg-white rounded-md hover:bg-slate-50"><ChevronLeft size={14}/></button>
               <span className="text-xs font-bold text-[#784421]">15.06 - 22.06</span>
               <button className="p-1.5 border border-[#e5d8c3] bg-white rounded-md hover:bg-slate-50"><ChevronLeft size={14} className="rotate-180"/></button>
            </div>
          </div>
          
          <div className="p-0 overflow-y-auto max-h-[600px] custom-scrollbar divide-y divide-slate-100">
             {activeInstances.map(inst => {
                const workshop = WORKSHOPS.find(w => w.id === inst.workshopId);
                const instReservations = RESERVATIONS_MOCK.filter(r => r.instanceId === inst.id);

                return (
                   <div key={inst.id} className="transition-colors">
                      <div className="p-4 flex justify-between items-center hover:bg-slate-50">
                         <div>
                            <div className="flex items-center gap-2 mb-1">
                               <DynamicLink type="workshop" id={workshop.id} label={workshop.title} navigate={navigate} className="text-base text-slate-800"/>
                            </div>
                            <div className="text-xs text-slate-500">
                               <DynamicLink type="date" id={inst.date} label={`${inst.date}, ${inst.time}`} navigate={navigate} className="text-slate-500 font-medium"/>
                            </div>
                         </div>
                         <div className="flex items-center gap-3">
                            <span className={`text-xs font-bold px-2 py-1 rounded-full border ${inst.spotsTaken >= inst.totalSpots ? 'bg-red-50 text-red-600 border-red-200' : 'bg-[#fdf8ee] text-[#784421] border-[#e5d8c3]'}`}>
                               {inst.spotsTaken} / {inst.totalSpots} miejsc
                            </span>
                            <button onClick={() => navigate({view: 'workshop_detail', id: workshop.id})} className="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition" title="Zarządzaj">
                                <Eye size={16}/>
                            </button>
                         </div>
                      </div>
                   </div>
                );
             })}
          </div>
        </div>

        {/* Kolumna 3: Wymagają uwagi (Nieopłacone/Porzucone) */}
        <div className="bg-white rounded-3xl border border-[#e5d8c3] shadow-sm flex flex-col h-[600px]">
          <div className="p-5 border-b border-red-100 bg-red-50/50 rounded-t-3xl">
            <h3 className="font-bold text-red-800 flex items-center gap-2"><AlertTriangle size={18}/> Wymagają uwagi</h3>
            <p className="text-xs text-red-600 mt-1">Rezerwacje nieopłacone w terminie i przedawnione.</p>
          </div>
          <div className="p-0 overflow-y-auto custom-scrollbar">
            <div className="divide-y divide-slate-100">
               {problemReservations.map((res, i) => {
                  const inst = WORKSHOP_INSTANCES.find(inst => inst.id === res.instanceId);
                  const workshop = inst ? WORKSHOPS.find(w => w.id === inst.workshopId) : null;
                  
                  return (
                     <div key={i} className="p-4 hover:bg-slate-50 transition border-l-4 border-transparent hover:border-red-400">
                        <div className="flex justify-between items-start mb-1">
                           <DynamicLink type="client" id={res.clientId} label={res.parentName} navigate={navigate} className="text-sm text-slate-800"/>
                           <span className={`text-[10px] font-bold px-2 py-0.5 rounded ${res.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600'}`}>
                              {res.status === 'pending' ? 'Oczekuje (Zalega)' : 'Przedawnione'}
                           </span>
                        </div>
                        {workshop && (
                           <div className="text-xs text-slate-500 mb-2">
                              Dotyczy: <DynamicLink type="workshop" id={workshop.id} label={workshop.title} navigate={navigate} className="font-medium text-slate-600" /> <br/>
                              Termin: <DynamicLink type="date" id={inst.date} label={inst.date} navigate={navigate} className="font-medium text-slate-600" />
                           </div>
                        )}
                        <div className="flex gap-2">
                           {res.status === 'pending' && <button className="flex-1 text-[10px] font-bold bg-white border border-slate-200 py-1.5 rounded text-slate-600 hover:bg-slate-50">Zatwierdź ręcznie</button>}
                           {res.status !== 'expired' && <button className="flex-1 text-[10px] font-bold bg-red-50 border border-red-100 py-1.5 rounded text-red-600 hover:bg-red-100">Anuluj systemowo</button>}
                        </div>
                     </div>
                  )
               })}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function AdminWorkshopDetails({ id, navigate }) {
   const workshop = WORKSHOPS.find(w => w.id === id) || WORKSHOPS[0];
   const allInstances = WORKSHOP_INSTANCES.filter(i => i.workshopId === workshop.id);
   
   // Sortowanie i podział na przeszłe/przyszłe (prosta symulacja dat)
   const pastInstances = allInstances.filter(i => i.status === 'completed');
   const upcomingInstances = allInstances.filter(i => i.status === 'active');

   const [showPast, setShowPast] = useState(false);
   const [expandedInstance, setExpandedInstance] = useState(upcomingInstances[0]?.id);

   // Złączona lista (na górze ładujemy przeszłe, jeśli showPast = true)
   const displayInstances = [...(showPast ? pastInstances : []), ...upcomingInstances];

   // Pomocnik do kolorów statusów rezerwacji
   const getStatusBadge = (status) => {
      switch(status) {
         case 'paid': return <span className="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-1 rounded uppercase">Opłacone</span>;
         case 'pending': return <span className="bg-amber-100 text-amber-700 text-[10px] font-bold px-2 py-1 rounded border border-amber-200 uppercase" title="Oczekuje na wpłatę, ale blokuje miejsce.">Oczekuje (Blokuje)</span>;
         case 'waitlist': return <span className="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-1 rounded uppercase border border-blue-200">Rezerwowa</span>;
         case 'cancelled': return <span className="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-1 rounded uppercase">Anulowane</span>;
         case 'transferred': return <span className="bg-purple-100 text-purple-700 text-[10px] font-bold px-2 py-1 rounded uppercase">Przeniesione</span>;
         default: return <span className="bg-slate-200 text-slate-600 text-[10px] font-bold px-2 py-1 rounded uppercase">{status}</span>;
      }
   }

   return (
      <div className="space-y-6 animate-in fade-in duration-300">
         {/* HEADER WARSZTATU */}
         <div className="bg-white p-6 rounded-3xl border border-[#e5d8c3] shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
            <div className={`absolute left-0 top-0 bottom-0 w-2 ${workshop.img}`}></div>
            <div className="pl-4">
               <div className="flex items-center gap-3 mb-2">
                  <span className={`px-2.5 py-1 rounded-md text-xs font-bold ${workshop.badge} ${workshop.color}`}>{workshop.age}</span>
                  <span className="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-md flex items-center gap-1"><Repeat size={12}/> {workshop.repeatability}</span>
               </div>
               <h1 className="text-2xl font-black text-slate-800">{workshop.title}</h1>
               <p className="text-sm text-slate-500 mt-1">Prowadzi: <b>{workshop.instructor}</b></p>
            </div>
            <div className="flex gap-2 shrink-0">
               <button className="px-4 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold flex items-center gap-2 transition"><Edit size={16}/> Edytuj szablon</button>
            </div>
         </div>

         {/* OŚ CZASU (Wystąpienia) */}
         <div className="bg-white rounded-3xl border border-[#e5d8c3] shadow-sm overflow-hidden">
            <div className="p-5 border-b border-[#e5d8c3] bg-[#fdf8ee] flex justify-between items-center">
               <h3 className="font-bold text-[#784421] text-lg flex items-center gap-2"><CalendarDays size={20}/> Harmonogram i Rezerwacje</h3>
               {!showPast && pastInstances.length > 0 && (
                  <button onClick={() => setShowPast(true)} className="text-sm font-bold text-[#eb4a54] hover:text-red-700 flex items-center gap-2 bg-white px-3 py-1.5 rounded-lg border border-[#e5d8c3] shadow-sm">
                     <ArrowDownToLine size={16}/> Załaduj historyczne zajęcia ({pastInstances.length})
                  </button>
               )}
            </div>

            <div className="p-6 relative">
               {/* Linia osi czasu */}
               <div className="absolute left-10 top-6 bottom-6 w-0.5 bg-slate-200 hidden md:block"></div>

               <div className="space-y-6 relative">
                  {displayInstances.map((inst, index) => {
                     const isPast = inst.status === 'completed';
                     const isExpanded = expandedInstance === inst.id;
                     const instRes = RESERVATIONS_MOCK.filter(r => r.instanceId === inst.id);
                     const waitlist = instRes.filter(r => r.status === 'waitlist');
                     const activeRes = instRes.filter(r => r.status !== 'waitlist' && r.status !== 'cancelled' && r.status !== 'expired');

                     return (
                        <div key={inst.id} className="relative md:pl-16">
                           {/* Kropka osi czasu */}
                           <div className={`absolute left-[-26px] top-5 w-4 h-4 rounded-full border-4 border-white hidden md:block ${isPast ? 'bg-slate-300' : 'bg-[#eb4a54]'}`}></div>

                           <div className={`border-2 rounded-2xl transition-all duration-300 overflow-hidden ${isExpanded ? 'border-indigo-300 shadow-md ring-4 ring-indigo-50' : 'border-[#e5d8c3] hover:border-indigo-200'} ${isPast && !isExpanded ? 'opacity-75 bg-slate-50' : 'bg-white'}`}>
                              
                              {/* Nagłówek Wystąpienia */}
                              <div className="p-4 sm:p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 cursor-pointer" onClick={() => setExpandedInstance(isExpanded ? null : inst.id)}>
                                 <div className="flex items-center gap-4">
                                    <div className={`p-3 rounded-xl font-bold text-center min-w-[70px] ${isPast ? 'bg-slate-200 text-slate-600' : 'bg-indigo-50 text-indigo-700'}`}>
                                       {inst.time.split(' - ')[0]}
                                    </div>
                                    <div>
                                       <div className="font-black text-lg text-slate-800">{inst.date} {isPast && <span className="text-xs text-slate-400 font-normal ml-2">(Zakończone)</span>}</div>
                                       <div className="text-sm font-medium text-slate-500">Zajętość: {inst.spotsTaken} / {inst.totalSpots} miejsc</div>
                                    </div>
                                 </div>
                                 <div className="flex items-center gap-4 w-full sm:w-auto">
                                    {/* Mini pasek postępu */}
                                    <div className="flex-1 sm:w-32 h-2 bg-slate-100 rounded-full overflow-hidden">
                                       <div className={`h-full ${inst.spotsTaken >= inst.totalSpots ? 'bg-red-500' : 'bg-green-500'}`} style={{width: `${(inst.spotsTaken/inst.totalSpots)*100}%`}}></div>
                                    </div>
                                    <ChevronLeft className={`text-slate-400 transition-transform ${isExpanded ? '-rotate-90' : 'rotate-180'}`}/>
                                 </div>
                              </div>

                              {/* Sekcja Rozwinięta: Rezerwacje */}
                              {isExpanded && (
                                 <div className="border-t border-slate-100 bg-slate-50">
                                    <div className="p-3 border-b border-slate-100 flex justify-between items-center bg-slate-100/50">
                                       <span className="text-xs font-bold text-slate-500 uppercase tracking-wider pl-2">Lista obecności ({activeRes.length})</span>
                                       {/* GRILL ME: Proaktywne rozwiązanie - Kopiowanie maili */}
                                       <button className="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 bg-white px-2 py-1 rounded border border-indigo-100 shadow-sm transition">
                                          <Copy size={12}/> Skopiuj maile grupy (BCC)
                                       </button>
                                    </div>
                                    
                                    <div className="divide-y divide-slate-100">
                                       {activeRes.map(res => (
                                          <div key={res.id} className="p-3 pl-5 flex flex-col md:flex-row justify-between md:items-center gap-3 hover:bg-white transition group">
                                             <div className="flex items-center gap-3">
                                                <div className="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">{res.child.charAt(0)}</div>
                                                <div>
                                                   <div className="font-bold text-sm text-slate-800">{res.child} <span className="text-xs text-slate-400 font-normal">({res.age})</span></div>
                                                   {/* Hover dla danych rodzica */}
                                                   <div className="relative group/parent inline-block">
                                                      <span className="text-[10px] text-slate-500 cursor-help border-b border-dashed border-slate-300">Rodzic: {res.parentName}</span>
                                                      <div className="absolute left-0 bottom-full mb-1 hidden group-hover/parent:block z-10 w-48 bg-slate-800 text-white p-2 rounded-lg shadow-xl text-xs space-y-1">
                                                         <div className="flex items-center gap-1"><Mail size={10}/> {res.email}</div>
                                                         <div className="flex items-center gap-1"><Phone size={10}/> {res.phone}</div>
                                                      </div>
                                                   </div>
                                                </div>
                                             </div>
                                             
                                             <div className="flex flex-wrap items-center gap-3">
                                                <div className="text-xs font-medium text-slate-600 bg-slate-100 px-2 py-1 rounded">{res.ticket}</div>
                                                {getStatusBadge(res.status)}
                                                
                                                {/* Akcje per rezerwacja */}
                                                <div className="flex gap-1 ml-auto opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                                   {res.status === 'pending' && (
                                                      <button className="text-[10px] font-bold bg-green-50 text-green-700 border border-green-200 px-2 py-1 rounded hover:bg-green-100 flex items-center gap-1" title="Potwierdź wpłatę ręcznie">
                                                         <Check size={12}/> Zatwierdź
                                                      </button>
                                                   )}
                                                   <button className="p-1 text-slate-400 hover:text-indigo-600 bg-white rounded border shadow-sm" title="Przenieś"><RotateCcw size={14}/></button>
                                                   <button className="p-1 text-slate-400 hover:text-red-600 bg-white rounded border shadow-sm" title="Anuluj i zwolnij miejsce"><Ban size={14}/></button>
                                                </div>
                                             </div>
                                          </div>
                                       ))}

                                       {activeRes.length === 0 && <div className="p-4 text-center text-sm text-slate-500">Brak aktywnych rezerwacji.</div>}
                                    </div>

                                    {/* GRILL ME: Sekcja Listy Rezerwowej */}
                                    {waitlist.length > 0 && (
                                       <div className="mt-2 border-t border-blue-100 bg-blue-50/50">
                                          <div className="p-2 border-b border-blue-100 pl-4 text-xs font-bold text-blue-700 uppercase tracking-wider flex items-center gap-2">
                                             Lista Rezerwowa ({waitlist.length}) <span className="bg-blue-100 px-1.5 py-0.5 rounded text-[9px] lowercase font-medium">nie blokują miejsc</span>
                                          </div>
                                          <div className="divide-y divide-blue-50">
                                             {waitlist.map(res => (
                                                <div key={res.id} className="p-3 pl-5 flex justify-between items-center gap-3 hover:bg-blue-50 transition">
                                                   <div>
                                                      <div className="font-bold text-sm text-slate-800">{res.child}</div>
                                                      <div className="text-[10px] text-slate-500">{res.parentName} • {res.phone}</div>
                                                   </div>
                                                   <button className="text-[10px] font-bold bg-white text-blue-600 border border-blue-200 px-3 py-1.5 rounded-lg shadow-sm hover:bg-blue-600 hover:text-white transition">
                                                      Przenieś do głównych (Awansuj)
                                                   </button>
                                                </div>
                                             ))}
                                          </div>
                                       </div>
                                    )}

                                 </div>
                              )}
                           </div>
                        </div>
                     );
                  })}
               </div>
            </div>
         </div>
      </div>
   );
}

function AdminWorkshops({ navigate }) {
  const [isModalOpen, setIsModalOpen] = useState(false);

  return (
    <>
      <div className="bg-white rounded-3xl border border-[#e5d8c3] shadow-sm flex flex-col">
        <div className="p-5 border-b border-[#e5d8c3] flex justify-between items-center bg-[#fdf8ee] rounded-t-3xl sticky top-0">
          <div className="flex gap-4">
            <div className="relative">
              <Search className="absolute left-3 top-2.5 text-[#784421]/50" size={16} />
              <input type="text" placeholder="Szukaj warsztatu..." className="pl-9 pr-4 py-2 bg-white border border-[#e5d8c3] rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#eb4a54]" />
            </div>
          </div>
          <button onClick={() => setIsModalOpen(true)} className="bg-[#eb4a54] hover:bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 transition shadow-sm">
            <Plus size={16} /> Dodaj nowy szablon zajęć
          </button>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm whitespace-nowrap">
            <thead className="bg-white text-slate-500 border-b border-[#e5d8c3]">
              <tr>
                <th className="px-6 py-4 font-semibold">Szablon Warsztatu</th>
                <th className="px-6 py-4 font-semibold">Wiek</th>
                <th className="px-6 py-4 font-semibold">Powtarzalność</th>
                <th className="px-6 py-4 font-semibold text-right">Standardowa Cena</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {WORKSHOPS.map(w => (
                <tr key={w.id} className="hover:bg-slate-50/50 transition cursor-pointer group" onClick={() => navigate({view: 'workshop_detail', id: w.id})}>
                  <td className="px-6 py-4">
                    <div className="font-bold text-slate-800 text-base group-hover:text-indigo-600 transition-colors">{w.title}</div>
                    <div className="text-xs text-slate-500 mt-1 max-w-[250px] truncate">{w.description}</div>
                  </td>
                  <td className="px-6 py-4"><span className={`px-2.5 py-1 rounded-md text-xs font-bold ${w.badge} ${w.color}`}>{w.age}</span></td>
                  <td className="px-6 py-4"><span className="bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-xs font-bold">{w.repeatability}</span></td>
                  <td className="px-6 py-4 text-right font-bold text-slate-800">{w.price} zł</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      {isModalOpen && <WorkshopEditorModal onClose={() => setIsModalOpen(false)} />}
    </>
  );
}

function WorkshopEditorModal({ onClose }) {
  const [activeTab, setActiveTab] = useState('general');
  return (
    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-[#faf8f5] w-full max-w-4xl rounded-[2rem] shadow-2xl flex flex-col max-h-[90vh] overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div className="flex justify-between items-center p-6 border-b border-[#e5d8c3] bg-white">
          <div>
            <h2 className="text-xl font-black text-[#784421]">Kreator Warsztatów</h2>
            <p className="text-sm text-slate-500">Zdefiniuj ustawienia i wygeneruj harmonogram.</p>
          </div>
          <button onClick={onClose} className="p-2 text-slate-400 hover:bg-slate-100 rounded-full transition"><X size={20} /></button>
        </div>
        <div className="flex px-6 border-b border-[#e5d8c3] bg-white pt-2">
          <button onClick={() => setActiveTab('general')} className={`px-4 py-3 text-sm font-bold border-b-[3px] transition ${activeTab === 'general' ? 'border-[#eb4a54] text-[#eb4a54]' : 'border-transparent text-slate-500 hover:text-slate-800'}`}>Podstawowe</button>
          <button onClick={() => setActiveTab('schedule')} className={`px-4 py-3 text-sm font-bold border-b-[3px] transition ${activeTab === 'schedule' ? 'border-[#eb4a54] text-[#eb4a54]' : 'border-transparent text-slate-500 hover:text-slate-800'}`}>Harmonogram</button>
          <button onClick={() => setActiveTab('tickets')} className={`px-4 py-3 text-sm font-bold border-b-[3px] transition ${activeTab === 'tickets' ? 'border-[#eb4a54] text-[#eb4a54]' : 'border-transparent text-slate-500 hover:text-slate-800'}`}>Bilety/Karnety</button>
        </div>
        <div className="p-8 overflow-y-auto custom-scrollbar flex-1 bg-white">
           {activeTab === 'general' && <p className="text-slate-500">Pola nazwy, opisu i kategorii.</p>}
           {activeTab === 'schedule' && <p className="text-slate-500">Konfiguracja cykliczności (dni tygodnia, wykluczanie świąt).</p>}
           {activeTab === 'tickets' && <p className="text-slate-500">Definiowanie karnetów i reguł odrabiania zajęć.</p>}
        </div>
        <div className="p-6 border-t border-[#e5d8c3] bg-white flex justify-end gap-3 rounded-b-[2rem]">
          <button onClick={onClose} className="px-6 py-3 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition">Anuluj</button>
          <button onClick={onClose} className="px-8 py-3 bg-[#eb4a54] hover:bg-red-600 text-white text-sm font-bold rounded-xl shadow-md transition">Zapisz Szablon</button>
        </div>
      </div>
    </div>
  );
}

function AdminDailySchedule({ date, navigate }) {
   const safeDate = date || CURRENT_DATE_STRING;
   const dayInstances = WORKSHOP_INSTANCES.filter(i => i.date === safeDate);

   return (
      <div className="bg-white rounded-3xl border border-[#e5d8c3] shadow-sm p-8">
         <div className="flex items-center gap-4 mb-6">
            <div className="w-16 h-16 bg-[#fdf8ee] text-[#784421] rounded-2xl flex items-center justify-center font-black text-2xl border border-[#e5d8c3]">{safeDate.split('.')[0]}</div>
            <div>
               <h2 className="text-2xl font-black text-slate-800">Plan Dnia: {safeDate}</h2>
               <p className="text-slate-500">Przegląd wszystkich zajęć odbywających się w tym dniu.</p>
            </div>
         </div>
         <div className="space-y-4">
            {dayInstances.map(inst => {
               const workshop = WORKSHOPS.find(w => w.id === inst.workshopId);
               return (
                  <div key={inst.id} className="border border-slate-200 rounded-2xl p-5 flex flex-col md:flex-row justify-between items-center gap-4 hover:border-indigo-300 transition">
                     <div className="flex items-center gap-4">
                        <div className="bg-indigo-50 text-indigo-700 font-bold px-4 py-2 rounded-xl border border-indigo-100">{inst.time.split(' - ')[0]}</div>
                        <div>
                           <DynamicLink type="workshop" id={workshop.id} label={workshop.title} navigate={navigate} className="text-lg text-slate-800 block mb-1"/>
                           <div className="text-sm text-slate-500">Prowadzi: {workshop.instructor}</div>
                        </div>
                     </div>
                     <button onClick={() => navigate({view: 'workshop_detail', id: workshop.id})} className="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-sm rounded-xl hover:bg-slate-200">Przejdź do listy</button>
                  </div>
               )
            })}
         </div>
      </div>
   )
}

function AdminReservations() { return <div className="p-8 text-center text-slate-500 bg-white rounded-3xl border border-[#e5d8c3]">Tabela wszystkich rezerwacji. Zaimplementowana we wcześniejszej iteracji.</div>; }
function AdminClients({ navigate }) { return <div className="p-8 text-center text-slate-500 bg-white rounded-3xl border border-[#e5d8c3]">Baza Klientów (CRM). Zaimplementowana we wcześniejszej iteracji.</div>; }
function AdminClientDetails({ id }) { return <div className="p-8 text-center text-slate-500 bg-white rounded-3xl border border-[#e5d8c3]">Karta Klienta {id} i funkcja Impersonate. Zaimplementowane we wcześniejszej iteracji.</div>; }

function AdminTransfers() { 
  return (
    <div className="space-y-6">
      <div className="bg-white p-5 rounded-3xl border border-[#e5d8c3] shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
        <h3 className="font-bold text-slate-800">Moduł Przelewów Bankowych</h3>
        <button className="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 transition shadow-sm">
          <FileText size={16} /> Importuj plik CSV banku
        </button>
      </div>
      <div className="bg-white rounded-3xl border border-[#e5d8c3] shadow-sm overflow-hidden">
        <table className="w-full text-left text-sm whitespace-nowrap">
          <thead className="bg-[#fdf8ee] text-slate-500 border-b border-[#e5d8c3]">
            <tr>
              <th className="px-6 py-4 font-semibold">Tytuł przelewu i Data</th>
              <th className="px-6 py-4 font-semibold">Nadawca</th>
              <th className="px-6 py-4 font-semibold">Status Dopasowania</th>
              <th className="px-6 py-4 font-semibold text-right">Kwota</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
             {MOCK_TRANSFERS.map(tr => (
                <tr key={tr.id} className="hover:bg-slate-50 transition">
                   <td className="px-6 py-4">
                      <div className="font-bold text-slate-800">{tr.title}</div>
                      <div className="text-xs text-slate-400">{tr.date}</div>
                   </td>
                   <td className="px-6 py-4">{tr.sender}</td>
                   <td className="px-6 py-4">
                      {tr.status === 'matched' 
                        ? <span className="text-xs font-bold bg-green-100 text-green-700 px-2 py-1 rounded">Dopasowano (Auto - prowizja 2%)</span>
                        : <button className="text-xs font-bold bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg hover:bg-indigo-100">Dopasuj ręcznie (0% prowizji)</button>
                      }
                   </td>
                   <td className="px-6 py-4 text-right font-bold">{tr.amount} zł</td>
                </tr>
             ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function AdminSettings() {
   const [activeTab, setActiveTab] = useState('roles');

   return (
      <div className="bg-white rounded-3xl border border-[#e5d8c3] shadow-sm flex flex-col min-h-[600px] overflow-hidden">
         <div className="flex flex-col md:flex-row h-full">
            {/* Sidebar Ustawień */}
            <div className="w-full md:w-72 bg-[#faf8f5] border-r border-[#e5d8c3] flex flex-col p-4 gap-2 shrink-0">
               <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 px-4 mt-2">Zarządzanie Zespołem</div>
               <button onClick={() => setActiveTab('roles')} className={`text-left px-4 py-3 rounded-xl text-sm font-bold transition ${activeTab === 'roles' ? 'bg-white shadow-sm text-[#eb4a54] border border-[#e5d8c3]' : 'text-slate-600 hover:bg-slate-200/50'}`}>Użytkownicy i Uprawnienia</button>
               
               <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 px-4 mt-4">System i Księgowość</div>
               <button onClick={() => setActiveTab('billing')} className={`text-left px-4 py-3 rounded-xl text-sm font-bold transition ${activeTab === 'billing' ? 'bg-white shadow-sm text-[#eb4a54] border border-[#e5d8c3]' : 'text-slate-600 hover:bg-slate-200/50'}`}>Odbiorcy raportów fin.</button>
               <button onClick={() => setActiveTab('seo')} className={`text-left px-4 py-3 rounded-xl text-sm font-bold transition ${activeTab === 'seo' ? 'bg-white shadow-sm text-[#eb4a54] border border-[#e5d8c3]' : 'text-slate-600 hover:bg-slate-200/50'}`}>SEO i robots.txt</button>
            </div>

            {/* Kontent Ustawień */}
            <div className="flex-1 p-8 overflow-y-auto">
               
               {activeTab === 'roles' && (
                  <div className="space-y-6 animate-in fade-in">
                     <div>
                        <h2 className="text-xl font-black text-slate-800">Administratorzy i Prowadzący</h2>
                        <p className="text-sm text-slate-500 mt-1">Nadawaj uprawnienia użytkownikom. Prowadzący mają dostęp tylko do list obecności na swoich zajęciach, Administratorzy mają pełen dostęp.</p>
                     </div>
                     <div className="space-y-4">
                        {/* Grupa Administratorów */}
                        <div className="border border-slate-200 rounded-2xl overflow-hidden">
                           <div className="bg-slate-50 p-4 border-b border-slate-200 font-bold text-slate-800 flex items-center gap-2"><ShieldAlert size={18} className="text-indigo-600"/> Administratorzy Systemu</div>
                           <div className="p-4 flex justify-between items-center hover:bg-slate-50">
                              <div>
                                 <div className="font-bold text-sm">Michał W.</div>
                                 <div className="text-xs text-slate-500">do@mleczki.pl</div>
                              </div>
                              <span className="bg-slate-200 text-slate-600 text-[10px] font-bold px-2 py-1 rounded">Super Admin</span>
                           </div>
                           <div className="p-4 border-t border-slate-100 flex justify-between items-center hover:bg-slate-50">
                              <div>
                                 <div className="font-bold text-sm">Katarzyna Z.</div>
                                 <div className="text-xs text-slate-500">kasia@warsztatownia.pl</div>
                              </div>
                              <button className="text-xs font-bold text-red-600 hover:underline">Odbierz dostęp</button>
                           </div>
                        </div>

                        {/* Grupa Prowadzących */}
                        <div className="border border-slate-200 rounded-2xl overflow-hidden mt-6">
                           <div className="bg-slate-50 p-4 border-b border-slate-200 font-bold text-slate-800 flex justify-between items-center">
                              <span className="flex items-center gap-2"><Users size={18} className="text-[#eb4a54]"/> Prowadzący Zajęcia</span>
                              <button className="text-xs font-bold text-indigo-600 flex items-center gap-1"><Plus size={14}/> Dodaj prowadzącego</button>
                           </div>
                           <div className="p-4 flex justify-between items-center hover:bg-slate-50">
                              <div>
                                 <div className="font-bold text-sm">Anna K.</div>
                                 <div className="text-xs text-slate-500">Otrzymuje powiadomienia o rezerwacjach: Błotna Kuchnia</div>
                              </div>
                              <button className="p-1.5 text-slate-400 hover:text-indigo-600 border rounded"><Sliders size={14}/></button>
                           </div>
                           <div className="p-4 border-t border-slate-100 flex justify-between items-center hover:bg-slate-50">
                              <div>
                                 <div className="font-bold text-sm">Marta W.</div>
                                 <div className="text-xs text-slate-500">Otrzymuje powiadomienia o rezerwacjach: Sensoplastyka</div>
                              </div>
                              <button className="p-1.5 text-slate-400 hover:text-indigo-600 border rounded"><Sliders size={14}/></button>
                           </div>
                        </div>
                     </div>
                  </div>
               )}

               {activeTab === 'billing' && (
                  <div className="space-y-6 animate-in fade-in">
                     <div>
                        <h2 className="text-xl font-black text-slate-800">Kontakty Finansowe i Fakturowanie</h2>
                        <p className="text-sm text-slate-500 mt-1">Kto powinien otrzymywać na email automatyczne zestawienia prowizji (2%) oraz raporty niezidentyfikowanych przelewów?</p>
                     </div>
                     <div className="bg-[#faf8f5] p-5 rounded-2xl border border-[#e5d8c3] max-w-lg space-y-4">
                        <div className="flex items-center gap-3 bg-white p-3 rounded-xl border border-[#e5d8c3] shadow-sm">
                           <Mail className="text-slate-400"/>
                           <input type="text" defaultValue="ksiegowosc@warsztatownia.pl" className="flex-1 bg-transparent text-sm font-bold text-slate-800 outline-none" />
                           <button className="text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition"><Trash2 size={16}/></button>
                        </div>
                        <div className="flex items-center gap-3 bg-white p-3 rounded-xl border border-[#e5d8c3] shadow-sm">
                           <Mail className="text-slate-400"/>
                           <input type="text" defaultValue="szef@warsztatownia.pl" className="flex-1 bg-transparent text-sm font-bold text-slate-800 outline-none" />
                           <button className="text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition"><Trash2 size={16}/></button>
                        </div>
                        <button className="text-sm font-bold text-indigo-600 flex items-center gap-2 hover:underline p-2"><Plus size={16}/> Dodaj kolejny adres e-mail</button>
                     </div>
                     <button className="px-6 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-bold shadow-sm hover:bg-slate-900 transition">Zapisz konfigurację</button>
                  </div>
               )}

               {activeTab === 'seo' && (
                  <div className="space-y-6 animate-in fade-in">
                     <div><h2 className="text-xl font-black text-slate-800">SEO & Pliki Systemowe</h2></div>
                     <div className="space-y-2">
                        <label className="text-sm font-bold text-slate-700 flex items-center gap-2"><FileCode size={16}/> robots.txt</label>
                        <textarea rows="4" className="w-full bg-slate-900 text-green-400 font-mono text-sm p-4 rounded-xl outline-none" defaultValue={`User-agent: *\nAllow: /\nDisallow: /admin/`}></textarea>
                     </div>
                     <button className="px-6 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold">Zapisz robots.txt</button>
                  </div>
               )}
            </div>
         </div>
      </div>
   );
}