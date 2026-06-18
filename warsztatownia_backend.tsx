import React, { useState } from 'react';
import { 
  Calendar, Users, LayoutDashboard, ChevronLeft, CheckCircle, 
  CreditCard, Menu, Clock, Ticket, User, MapPin, Search, 
  Settings, Bell, Edit, Trash2, Plus, Filter,
  X, RefreshCw, Ban, ShieldAlert, FileText, DollarSign, Lock, Unlock, Repeat, Check,
  AlertTriangle, Mail, Phone, ExternalLink, CalendarDays, Eye, EyeOff, RotateCcw, Building, FileCode, Send
} from 'lucide-react';

const WORKSHOPS = [
  { id: 1, title: 'Sensoplastyka dla maluchów', age: '0-3 lat', price: 55, category: 'Sensoryka', img: 'bg-orange-100', color: 'text-orange-600', badge: 'bg-orange-200', description: 'Zajęcia ogólnorozwojowe z wykorzystaniem jadalnych materiałów. Brudzimy się na maksa!', instructor: 'Marta W.' },
  { id: 2, title: 'Błotna Kuchnia', age: '3-6 lat', price: 60, category: 'Brudna zabawa', img: 'bg-green-100', color: 'text-green-600', badge: 'bg-green-200', description: 'Bawimy się ziemią, wodą, liśćmi. Konstruujemy własne błotne potrawy w ogrodzie.', instructor: 'Anna K.' },
  { id: 3, title: 'Muzyczne Sensorki', age: '1-4 lat', price: 50, category: 'Muzyka', img: 'bg-purple-100', color: 'text-purple-600', badge: 'bg-purple-200', description: 'Zabawy rytmiczne i dźwiękowe wspomagające integrację sensoryczną i rozwój mowy.', instructor: 'Piotr Z.' }
];

const WORKSHOP_INSTANCES = {
  1: [
    { id: 'W1-I1', date: '15 Cze 2026', time: '10:00 - 11:30', totalSpots: 10, spotsTaken: 8 },
    { id: 'W1-I2', date: '22 Cze 2026', time: '10:00 - 11:30', totalSpots: 10, spotsTaken: 10 },
  ]
};

const RESERVATIONS_MOCK = [
  { id: 'RES-001', instanceId: 'W1-I1', child: 'Antosia', age: '2 lata', parentName: 'Magdalena Kowalska', email: 'magda@example.com', phone: '123456789', ticket: 'Jednorazowe', status: 'paid', amount: 55 },
  { id: 'RES-002', instanceId: 'W1-I1', child: 'Jaś', age: '3 lata', parentName: 'Anna Wiśniewska', email: 'anna@example.com', phone: '987654321', ticket: 'Karnet 4-wejść (1/4)', status: 'paid', amount: 0 },
  { id: 'RES-003', instanceId: 'W1-I1', child: 'Zosia', age: '1.5 roku', parentName: 'Piotr Nowak', email: 'piotr@example.com', phone: '555666777', ticket: 'Jednorazowe', status: 'pending', amount: 55 },
  { id: 'RES-004', instanceId: 'W1-I1', child: 'Krzyś', age: '2 lata', parentName: 'Ewa Zielińska', email: 'ewa@example.com', phone: '111222333', ticket: 'Jednorazowe', status: 'transferred', amount: 55, note: 'Przeniesione na 22 Cze' },
  { id: 'RES-005', instanceId: 'W1-I1', child: 'Maja', age: '2 lata', parentName: 'Kamil K.', email: 'kamil@example.com', phone: '000000000', ticket: 'Jednorazowe', status: 'cancelled', amount: 55 },
  { id: 'RES-006', instanceId: 'W1-I1', child: 'Olaf', age: '3 lata', parentName: 'Julia M.', email: 'julia@example.com', phone: '111111111', ticket: 'Jednorazowe', status: 'expired', amount: 55 }
];

const MOCK_CLIENTS = [
  { id: 'C-001', name: 'Magdalena Kowalska', email: 'magda@example.com', phone: '+48 123 456 789', registered: '10.01.2024', status: 'active', children: [{name: 'Antosia', age: '2l'}, {name: 'Igor', age: '5l'}] },
  { id: 'C-002', name: 'Piotr Nowak', email: 'piotr.n@example.com', phone: '+48 987 654 321', registered: '15.03.2024', status: 'active', children: [{name: 'Janek', age: '4l'}] },
];

const PLATFORM_BILLING = {
  lastMonth: { month: 'Maj 2026', amount: 145.50, status: 'unpaid' },
  currentMonth: { month: 'Czerwiec 2026', estimated: 85.20, totalMatched: 4260.00 }
};

export default function WarsztatowniaApp() {
  const [viewMode, setViewMode] = useState('admin'); // Domyślnie Admin dla ułatwienia testów

  return (
    <div className="flex flex-col min-h-screen bg-slate-100 font-sans">
      <div className="bg-slate-800 text-white p-3 flex flex-wrap justify-center items-center gap-4 text-sm z-50 shadow-md">
        <span className="opacity-70 font-medium hidden sm:inline">Przełącznik makiety:</span>
        <div className="flex gap-2">
          <button onClick={() => setViewMode('client')} className={`px-4 py-2 rounded-lg transition-colors font-medium ${viewMode === 'client' ? 'bg-teal-500 text-white' : 'bg-slate-700 hover:bg-slate-600'}`}>
            📱 Klient (Mobilka)
          </button>
          <button onClick={() => setViewMode('admin')} className={`px-4 py-2 rounded-lg transition-colors font-medium ${viewMode === 'admin' ? 'bg-indigo-500 text-white' : 'bg-slate-700 hover:bg-slate-600'}`}>
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

// ==========================================
// CLIENT APP (Uproszczona na potrzeby makiety)
// ==========================================
function ClientApp() {
  return (
    <div className="w-full max-w-md bg-white min-h-[850px] shadow-2xl sm:my-8 sm:rounded-[2.5rem] relative overflow-hidden border-8 border-slate-800/10 flex flex-col items-center justify-center p-8 text-center">
        <h2 className="text-2xl font-black text-teal-600 mb-4">Widok Klienta</h2>
        <p className="text-gray-500">Aby przetestować nowo zaimplementowane funkcje CRM i paneli konfiguracyjnych, przełącz na <b>Panel Admina</b> używając górnego paska.</p>
    </div>
  )
}

// ==========================================
// ADMIN APP (DESKTOP DASHBOARD & CRM)
// ==========================================
function AdminApp() {
  const [navState, setNavState] = useState({ view: 'dashboard', id: null }); // view, id

  return (
    <div className="flex w-full min-h-screen bg-slate-50 text-slate-800">
      {/* Sidebar */}
      <aside className="w-64 bg-slate-900 text-slate-300 flex flex-col hidden md:flex shrink-0 z-20 shadow-xl">
        <div className="p-6">
          <h1 className="text-2xl font-black text-white tracking-tight">Kiddo<span className="text-indigo-500">.</span></h1>
          <p className="text-xs text-slate-500 uppercase tracking-wider font-bold mt-1">System Zarządzania</p>
        </div>
        
        <nav className="flex-1 px-4 space-y-1">
          <SidebarItem icon={<LayoutDashboard size={18}/>} label="Pulpit" active={navState.view === 'dashboard'} onClick={() => setNavState({view: 'dashboard', id: null})} />
          <SidebarItem icon={<Calendar size={18}/>} label="Warsztaty i Grafik" active={navState.view.includes('workshop')} onClick={() => setNavState({view: 'workshops', id: null})} />
          <SidebarItem icon={<Users size={18}/>} label="Baza Klientów (CRM)" active={navState.view.includes('client')} onClick={() => setNavState({view: 'clients', id: null})} />
          <SidebarItem icon={<DollarSign size={18}/>} label="Przelewy / Finanse" active={navState.view === 'transfers'} badge="2" onClick={() => setNavState({view: 'transfers', id: null})} />
        </nav>
        
        <div className="p-4 border-t border-slate-800">
          <SidebarItem icon={<Settings size={18}/>} label="Ustawienia Systemu" active={navState.view === 'settings'} onClick={() => setNavState({view: 'settings', id: null})} />
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col overflow-hidden h-screen relative">
        {/* Topbar */}
        <header className="bg-white h-16 border-b border-slate-200 flex items-center justify-between px-6 shrink-0 z-10 shadow-sm">
          <div className="font-bold text-lg text-slate-800 flex items-center gap-2">
            {navState.view === 'workshop_detail' && <button onClick={() => setNavState({view: 'workshops', id: null})} className="p-1.5 mr-2 bg-slate-100 rounded-lg hover:bg-slate-200"><ChevronLeft size={18}/></button>}
            {navState.view === 'client_detail' && <button onClick={() => setNavState({view: 'clients', id: null})} className="p-1.5 mr-2 bg-slate-100 rounded-lg hover:bg-slate-200"><ChevronLeft size={18}/></button>}
            
            {navState.view === 'dashboard' && 'Pulpit Główny'}
            {navState.view === 'workshops' && 'Zarządzanie Warsztatami'}
            {navState.view === 'workshop_detail' && 'Szczegóły Warsztatu i Lista Uczestników'}
            {navState.view === 'clients' && 'Baza Klientów (CRM)'}
            {navState.view === 'client_detail' && 'Karta Klienta'}
            {navState.view === 'transfers' && 'Przelewy bankowe'}
            {navState.view === 'settings' && 'Ustawienia i Konfiguracja'}
          </div>
          <div className="flex items-center gap-4">
            <button className="relative p-2 text-slate-400 hover:text-slate-600 transition">
              <Bell size={20} />
              <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
            </button>
            <div className="flex items-center gap-2 pl-4 border-l border-slate-200">
              <div className="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold text-sm">A</div>
              <span className="text-sm font-medium text-slate-700">Administrator</span>
            </div>
          </div>
        </header>

        {/* Dynamic Content */}
        <div className="flex-1 overflow-auto bg-slate-50/50 p-6 custom-scrollbar">
          <div className="max-w-7xl mx-auto space-y-6">
            {/* PLATFORM BILLING ALERT - CRITICAL */}
            {PLATFORM_BILLING.lastMonth.status === 'unpaid' && navState.view === 'dashboard' && (
              <div className="bg-red-50 border border-red-200 rounded-2xl p-4 shadow-sm flex items-start gap-4 animate-in fade-in slide-in-from-top-2">
                <div className="bg-red-100 p-2 rounded-full text-red-600 shrink-0"><AlertTriangle size={24}/></div>
                <div className="flex-1">
                  <h3 className="font-bold text-red-800 text-lg">Zaległa opłata systemowa za ubiegły miesiąc ({PLATFORM_BILLING.lastMonth.month})</h3>
                  <p className="text-red-700 text-sm mt-1">Zarejestrowaliśmy brak wpłaty prowizji (2% od automatycznie przypisanych przelewów). Kwota do zapłaty: <b>{PLATFORM_BILLING.lastMonth.amount} zł</b>.</p>
                </div>
                <button className="bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm transition whitespace-nowrap">
                  Opłać teraz (Przelewy24)
                </button>
              </div>
            )}

            {navState.view === 'dashboard' && <AdminDashboard navigate={setNavState} />}
            {navState.view === 'workshops' && <AdminWorkshops navigate={setNavState} />}
            {navState.view === 'workshop_detail' && <AdminWorkshopDetails id={navState.id} />}
            {navState.view === 'clients' && <AdminClients navigate={setNavState} />}
            {navState.view === 'client_detail' && <AdminClientDetails id={navState.id} />}
            {navState.view === 'transfers' && <AdminTransfers />}
            {navState.view === 'settings' && <AdminSettings />}
          </div>
        </div>
      </main>
    </div>
  );
}

function SidebarItem({ icon, label, active, onClick, badge }) {
  return (
    <button onClick={onClick} className={`w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors text-sm font-medium ${active ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800 hover:text-white'}`}>
      <div className="flex items-center gap-3">{icon}<span>{label}</span></div>
      {badge && <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${active ? 'bg-indigo-500' : 'bg-indigo-500/20 text-indigo-400'}`}>{badge}</span>}
    </button>
  );
}

function AdminDashboard({ navigate }) {
  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="text-sm font-semibold text-slate-500 mb-1">Rezerwacje (Czerwiec)</div>
          <div className="text-2xl font-black text-slate-800">142</div>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="text-sm font-semibold text-slate-500 mb-1">Przychód (Czerwiec)</div>
          <div className="text-2xl font-black text-slate-800">7 850 zł</div>
        </div>
        {/* WIDŻET ROZLICZEŃ Z PLATFORMĄ */}
        <div className="bg-gradient-to-br from-slate-800 to-slate-900 p-5 rounded-2xl shadow-lg text-white md:col-span-2 relative overflow-hidden">
          <Building className="absolute right-[-20px] bottom-[-20px] w-32 h-32 opacity-10 text-white" />
          <div className="flex justify-between items-start">
            <div>
              <div className="text-sm font-semibold text-slate-300 mb-1">Opłata systemowa (2% prowizji)</div>
              <div className="flex items-baseline gap-2">
                <span className="text-3xl font-black text-white">{PLATFORM_BILLING.currentMonth.estimated} zł</span>
                <span className="text-xs text-slate-400">Prognoza za {PLATFORM_BILLING.currentMonth.month}</span>
              </div>
            </div>
            <div className="bg-slate-800/50 px-3 py-1.5 rounded-lg border border-slate-700/50 backdrop-blur-sm">
               <div className="text-[10px] uppercase tracking-wider text-slate-400 font-bold mb-0.5">Stan konta (Maj)</div>
               <div className="flex items-center gap-1.5 text-sm font-bold text-red-400"><AlertTriangle size={14}/> Zaległość {PLATFORM_BILLING.lastMonth.amount} zł</div>
            </div>
          </div>
          <p className="text-[10px] text-slate-400 mt-4 leading-tight max-w-[80%]">Naliczane tylko dla płatności automatycznie dopasowanych ({PLATFORM_BILLING.currentMonth.totalMatched} zł). Ręczne przypisania w panelu Przelewów = 0% prowizji.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Calendar / Timeline Widget */}
        <div className="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col h-[400px]">
          <div className="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
            <h3 className="font-bold text-slate-800 flex items-center gap-2"><CalendarDays size={18} className="text-indigo-500"/> Kalendarz - Najbliższe dni</h3>
            <button onClick={() => navigate({view: 'workshops', id: null})} className="text-indigo-600 text-sm font-semibold hover:text-indigo-700">Zarządzaj grafikiem</button>
          </div>
          <div className="p-5 space-y-5 overflow-y-auto custom-scrollbar">
            {/* Timeline item */}
            <div className="relative pl-6 border-l-2 border-indigo-100 space-y-4">
               <div className="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-indigo-500 border-4 border-white"></div>
               <div className="font-bold text-slate-700 text-sm -mt-1 mb-3">Dziś (15 Czerwca)</div>
               
               <div className="bg-slate-50 rounded-xl p-4 border border-slate-100 flex gap-4 hover:shadow-md transition cursor-pointer" onClick={() => navigate({view: 'workshop_detail', id: 1})}>
                 <div className="w-16 bg-white border border-slate-200 rounded-lg flex flex-col items-center justify-center shrink-0">
                    <span className="text-xs font-bold text-slate-500">10:00</span>
                    <span className="text-[10px] text-slate-400">11:30</span>
                 </div>
                 <div className="flex-1">
                    <h4 className="font-bold text-slate-800 text-sm">Sensoplastyka dla maluchów</h4>
                    <p className="text-xs text-slate-500 mt-1">Prowadzi: Marta W. • 0-3 lat</p>
                 </div>
                 <div className="text-right">
                    <div className="text-xs font-bold text-green-600 mb-1">Miejsc: 8/10</div>
                    <span className="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold">Odbędzie się</span>
                 </div>
               </div>
            </div>
          </div>
        </div>

        {/* Recent Updates / Cancelled */}
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col h-[400px]">
          <div className="p-5 border-b border-slate-100">
            <h3 className="font-bold text-slate-800 flex items-center gap-2"><RefreshCw size={18} className="text-slate-400"/> Ostatnie zmiany klientów</h3>
          </div>
          <div className="p-0 overflow-y-auto custom-scrollbar">
            <div className="divide-y divide-slate-100">
               <div className="p-4 hover:bg-slate-50 transition">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span className="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded">Odwołana obecność</span>
                  </div>
                  <div className="text-sm font-bold text-slate-800">Jaś Kowalski (2l)</div>
                  <div className="text-xs text-slate-500 mt-0.5">Błotna Kuchnia (16.06, 16:30)</div>
                  <div className="text-[10px] text-slate-400 mt-2">Przez: Magdalena K. (Aplikacja) - zwrócono wejście na karnet.</div>
               </div>
               <div className="p-4 hover:bg-slate-50 transition">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="w-2 h-2 rounded-full bg-blue-400"></span>
                    <span className="text-xs font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded">Zapis na rezerwową</span>
                  </div>
                  <div className="text-sm font-bold text-slate-800">Antosia (3l)</div>
                  <div className="text-xs text-slate-500 mt-0.5">Sensoplastyka (15.06)</div>
               </div>
            </div>
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
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col">
        <div className="p-5 border-b border-slate-100 flex justify-between items-center bg-white rounded-t-2xl sticky top-0">
          <div className="flex gap-4">
            <div className="relative">
              <Search className="absolute left-3 top-2.5 text-slate-400" size={16} />
              <input type="text" placeholder="Szukaj warsztatu..." className="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
          <button onClick={() => setIsModalOpen(true)} className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition shadow-sm">
            <Plus size={16} /> Dodaj nowy szablon zajęć
          </button>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm whitespace-nowrap">
            <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
              <tr>
                <th className="px-6 py-4 font-semibold">Szablon Warsztatu</th>
                <th className="px-6 py-4 font-semibold">Wiek / Kategoria</th>
                <th className="px-6 py-4 font-semibold">Prowadzący</th>
                <th className="px-6 py-4 font-semibold text-right">Standardowa Cena</th>
                <th className="px-6 py-4 font-semibold text-right">Akcje</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {WORKSHOPS.map(w => (
                <tr key={w.id} className="hover:bg-slate-50/50 transition cursor-pointer group" onClick={() => navigate({view: 'workshop_detail', id: w.id})}>
                  <td className="px-6 py-4">
                    <div className="font-bold text-slate-800 text-base group-hover:text-indigo-600 transition-colors">{w.title}</div>
                    <div className="text-xs text-slate-500 mt-1 max-w-xs truncate">{w.description}</div>
                  </td>
                  <td className="px-6 py-4">
                    <span className={`px-2 py-1 rounded-md text-xs font-bold ${w.badge} ${w.color}`}>{w.age}</span>
                    <div className="text-xs text-slate-500 mt-1">{w.category}</div>
                  </td>
                  <td className="px-6 py-4 text-slate-600">{w.instructor}</td>
                  <td className="px-6 py-4 text-right font-bold text-slate-800">{w.price} zł</td>
                  <td className="px-6 py-4 text-right">
                    <button className="px-4 py-2 bg-slate-100 text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg text-xs font-bold transition">Zarządzaj grupami (Wystąpienia)</button>
                  </td>
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

function AdminWorkshopDetails({ id }) {
  const workshop = WORKSHOPS.find(w => w.id === id) || WORKSHOPS[0];
  const instances = WORKSHOP_INSTANCES[workshop.id] || [];
  const [expandedInstance, setExpandedInstance] = useState(instances[0]?.id);
  const [showExpired, setShowExpired] = useState(false);

  // Status Badge Helper
  const renderStatus = (status) => {
    switch(status) {
      case 'paid': return <span className="px-2 py-1 bg-green-100 text-green-700 rounded text-[10px] font-bold tracking-wide uppercase">Opłacone</span>;
      case 'pending': return <span className="px-2 py-1 bg-amber-100 text-amber-700 rounded text-[10px] font-bold tracking-wide uppercase">Oczekuje na wpłatę</span>;
      case 'transferred': return <span className="px-2 py-1 bg-purple-100 text-purple-700 rounded text-[10px] font-bold tracking-wide uppercase">Przeniesione</span>;
      case 'cancelled': return <span className="px-2 py-1 bg-red-100 text-red-700 rounded text-[10px] font-bold tracking-wide uppercase line-through">Anulowane</span>;
      case 'expired': return <span className="px-2 py-1 bg-slate-100 text-slate-500 rounded text-[10px] font-bold tracking-wide uppercase">Przedawnione</span>;
      default: return null;
    }
  };

  return (
    <div className="space-y-6 animate-in fade-in duration-300">
      {/* HEADER WARSZTATU */}
      <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
         <div className={`absolute left-0 top-0 bottom-0 w-2 ${workshop.img}`}></div>
         <div className="pl-2">
            <div className="flex items-center gap-3 mb-2">
               <span className={`px-2.5 py-1 rounded-md text-xs font-bold ${workshop.badge} ${workshop.color}`}>{workshop.age}</span>
               <span className="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-md">Prowadzący: {workshop.instructor}</span>
            </div>
            <h1 className="text-2xl font-black text-slate-800">{workshop.title}</h1>
            <p className="text-sm text-slate-500 mt-1 max-w-2xl">{workshop.description}</p>
         </div>
         <div className="flex gap-2 shrink-0">
            <button className="px-4 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold flex items-center gap-2 transition"><Edit size={16}/> Edytuj szablon</button>
         </div>
      </div>

      {/* LISTA INSTANCJI (Wystąpień) */}
      <div className="space-y-4">
        <h3 className="font-bold text-lg text-slate-800 px-1">Wystąpienia (Konkretne terminy zajęć)</h3>
        
        {instances.map(instance => {
          const isExpanded = expandedInstance === instance.id;
          // Filter reservations based on view state
          const instanceReservations = RESERVATIONS_MOCK.filter(r => r.instanceId === instance.id && (showExpired || r.status !== 'expired'));
          const paidCount = RESERVATIONS_MOCK.filter(r => r.instanceId === instance.id && r.status === 'paid').length;

          return (
            <div key={instance.id} className={`bg-white border rounded-2xl shadow-sm overflow-hidden transition-all duration-300 ${isExpanded ? 'border-indigo-300 ring-1 ring-indigo-100' : 'border-slate-200'}`}>
              {/* Instance Header (Clickable) */}
              <div 
                className={`p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 cursor-pointer hover:bg-slate-50 ${isExpanded ? 'bg-indigo-50/30' : ''}`}
                onClick={() => setExpandedInstance(isExpanded ? null : instance.id)}
              >
                <div className="flex items-center gap-4">
                   <div className="bg-indigo-100 text-indigo-700 p-3 rounded-xl"><CalendarDays size={24}/></div>
                   <div>
                      <div className="font-black text-lg text-slate-800">{instance.date}</div>
                      <div className="text-sm font-medium text-slate-500 flex items-center gap-2"><Clock size={14}/> {instance.time}</div>
                   </div>
                </div>
                
                <div className="flex items-center gap-6 w-full sm:w-auto">
                   <div className="flex-1 sm:w-48">
                      <div className="flex justify-between text-xs font-bold mb-1">
                         <span className="text-slate-600">Zajętość miejsc</span>
                         <span className={instance.spotsTaken >= instance.totalSpots ? 'text-red-500' : 'text-green-600'}>{instance.spotsTaken} / {instance.totalSpots}</span>
                      </div>
                      <div className="w-full bg-slate-200 rounded-full h-2">
                        <div className={`h-2 rounded-full ${instance.spotsTaken >= instance.totalSpots ? 'bg-red-500' : 'bg-green-500'}`} style={{width: `${(instance.spotsTaken/instance.totalSpots)*100}%`}}></div>
                      </div>
                   </div>
                   <button className="text-indigo-600 bg-indigo-50 p-2 rounded-lg"><ChevronLeft className={`transition-transform duration-300 ${isExpanded ? '-rotate-90' : 'rotate-180'}`}/></button>
                </div>
              </div>

              {/* Reservations List (Expanded Content) */}
              {isExpanded && (
                <div className="border-t border-slate-100 bg-white">
                  <div className="p-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                     <div className="flex gap-4 text-sm font-medium text-slate-600">
                        <span>Zarejestrowanych: <b>{instanceReservations.length}</b></span>
                        <span>Opłaconych: <b className="text-green-600">{paidCount}</b></span>
                     </div>
                     <div className="flex items-center gap-3">
                        <label className="flex items-center gap-2 text-xs font-semibold text-slate-500 cursor-pointer hover:text-slate-700">
                           <input type="checkbox" checked={showExpired} onChange={(e) => setShowExpired(e.target.checked)} className="rounded text-indigo-600 focus:ring-indigo-500" />
                           Pokaż przedawnione/ukryte
                        </label>
                        <button className="text-xs bg-white border border-slate-200 px-3 py-1.5 rounded-lg shadow-sm font-bold text-slate-700 flex items-center gap-2 hover:bg-slate-50">
                           <Mail size={14}/> Wyślij wiadomość do grupy
                        </button>
                     </div>
                  </div>
                  
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm whitespace-nowrap">
                      <thead className="bg-white text-slate-400 text-xs uppercase border-b border-slate-100">
                        <tr>
                          <th className="px-6 py-3 font-semibold w-1/3">Dziecko i Rodzic</th>
                          <th className="px-6 py-3 font-semibold">Typ Biletu</th>
                          <th className="px-6 py-3 font-semibold">Status Płatności</th>
                          <th className="px-6 py-3 font-semibold text-right">Zarządzaj</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-50">
                        {instanceReservations.map(res => (
                           <tr key={res.id} className={`hover:bg-slate-50 transition group ${res.status === 'cancelled' || res.status === 'expired' ? 'opacity-60 bg-slate-50/50' : ''}`}>
                              <td className="px-6 py-4">
                                 {/* Focus on CHILD, Parent as hover/secondary */}
                                 <div className="flex items-center gap-3">
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0 ${res.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600'}`}>
                                       {res.child.charAt(0)}
                                    </div>
                                    <div>
                                       <div className={`font-bold text-base ${res.status === 'cancelled' ? 'line-through text-slate-500' : 'text-slate-800'}`}>
                                          {res.child} <span className="text-xs text-slate-400 font-normal">({res.age})</span>
                                       </div>
                                       {/* HOVER TOOLTIP FOR PARENT */}
                                       <div className="relative group/parent">
                                          <div className="text-xs text-slate-500 flex items-center gap-1 cursor-help">
                                             Rodzic: {res.parentName} <ExternalLink size={10} className="text-slate-400"/>
                                          </div>
                                          {/* Tooltip Content */}
                                          <div className="absolute left-0 bottom-full mb-2 hidden group-hover/parent:block z-50 w-64 bg-slate-800 text-white p-3 rounded-xl shadow-xl text-xs space-y-2">
                                             <div className="font-bold border-b border-slate-600 pb-1">{res.parentName}</div>
                                             <div className="flex items-center gap-2"><Mail size={12}/> {res.email}</div>
                                             <div className="flex items-center gap-2"><Phone size={12}/> {res.phone}</div>
                                             <button className="mt-1 w-full bg-slate-700 hover:bg-slate-600 py-1 rounded transition text-center">Zobacz profil CRM</button>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                              </td>
                              <td className="px-6 py-4">
                                 <div className="font-medium text-slate-700">{res.ticket}</div>
                                 {res.amount > 0 && <div className="text-xs text-slate-400 font-mono">{res.amount} zł</div>}
                              </td>
                              <td className="px-6 py-4">
                                 {renderStatus(res.status)}
                                 {res.note && <div className="text-[10px] text-purple-600 mt-1 max-w-[120px] truncate" title={res.note}>{res.note}</div>}
                              </td>
                              <td className="px-6 py-4 text-right">
                                 {/* Akcje per rezerwacja */}
                                 <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    {(res.status === 'paid' || res.status === 'pending') && (
                                       <>
                                       <button title="Przenieś na inny termin" className="p-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition"><RotateCcw size={16}/></button>
                                       <button title="Zwróć środki (Refund)" className="p-1.5 text-orange-600 bg-orange-50 hover:bg-orange-100 rounded-lg transition"><DollarSign size={16}/></button>
                                       <button title="Anuluj" className="p-1.5 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition"><Ban size={16}/></button>
                                       </>
                                    )}
                                    {res.status === 'cancelled' && (
                                       <button title="Przywróć" className="p-1.5 text-green-600 bg-green-50 hover:bg-green-100 rounded-lg transition"><CheckCircle size={16}/></button>
                                    )}
                                 </div>
                              </td>
                           </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function AdminClients({ navigate }) {
  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col">
      <div className="p-5 border-b border-slate-100 flex justify-between items-center bg-white rounded-t-2xl sticky top-0">
        <div className="relative w-64">
          <Search className="absolute left-3 top-2.5 text-slate-400" size={16} />
          <input type="text" placeholder="Szukaj po nazwisku / dziecku..." className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </div>
      
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm whitespace-nowrap">
          <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
            <tr>
              <th className="px-6 py-4 font-semibold">Dane Rodzica</th>
              <th className="px-6 py-4 font-semibold">Dzieci (Profile)</th>
              <th className="px-6 py-4 font-semibold">Kontakt</th>
              <th className="px-6 py-4 font-semibold">Status</th>
              <th className="px-6 py-4 font-semibold text-right">Akcje</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {MOCK_CLIENTS.map(client => (
              <tr key={client.id} className="hover:bg-slate-50/50 transition cursor-pointer" onClick={() => navigate({view: 'client_detail', id: client.id})}>
                <td className="px-6 py-4">
                  <div className="font-bold text-slate-800 text-base">{client.name}</div>
                  <div className="text-xs text-slate-400 font-mono mt-0.5">ID: {client.id}</div>
                </td>
                <td className="px-6 py-4">
                  <div className="flex gap-1 flex-wrap max-w-[200px]">
                     {client.children.map((child, i) => (
                        <span key={i} className="px-2 py-0.5 bg-amber-50 border border-amber-100 text-amber-700 rounded text-xs font-bold">{child.name} ({child.age})</span>
                     ))}
                  </div>
                </td>
                <td className="px-6 py-4">
                  <div className="text-slate-800">{client.email}</div>
                  <div className="text-xs text-slate-500">{client.phone}</div>
                </td>
                <td className="px-6 py-4">
                  <span className={`px-2.5 py-1 rounded-md text-xs font-bold ${client.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                    Aktywne konto
                  </span>
                </td>
                <td className="px-6 py-4 text-right">
                  <button className="px-3 py-1.5 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg text-xs font-bold transition">Karta Klienta</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function AdminClientDetails({ id }) {
   const client = MOCK_CLIENTS.find(c => c.id === id) || MOCK_CLIENTS[0];

   return (
      <div className="space-y-6 animate-in fade-in duration-300">
         {/* Profil Header */}
         <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-start gap-6">
            <div className="w-20 h-20 bg-indigo-100 rounded-2xl flex items-center justify-center text-indigo-600 font-black text-3xl">
               {client.name.charAt(0)}
            </div>
            <div className="flex-1">
               <div className="flex justify-between items-start">
                  <div>
                     <h2 className="text-2xl font-black text-slate-800">{client.name}</h2>
                     <p className="text-sm text-slate-500 flex items-center gap-4 mt-1">
                        <span className="flex items-center gap-1"><Mail size={14}/> {client.email}</span>
                        <span className="flex items-center gap-1"><Phone size={14}/> {client.phone}</span>
                     </p>
                  </div>
                  <div className="flex gap-2">
                     <button className="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-sm font-bold hover:bg-slate-200 transition">Wyślij Email</button>
                     <button className="px-4 py-2 bg-red-50 text-red-600 rounded-xl text-sm font-bold hover:bg-red-100 transition"><Ban size={16} className="inline mr-1"/> Zablokuj</button>
                  </div>
               </div>

               <div className="mt-6 pt-4 border-t border-slate-100">
                  <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Zapisane Profile Dzieci</h4>
                  <div className="flex gap-3">
                     {client.children.map((child, i) => (
                        <div key={i} className="flex items-center gap-3 bg-slate-50 border border-slate-200 px-4 py-2 rounded-xl">
                           <div className="w-8 h-8 bg-amber-100 text-amber-700 rounded-full flex items-center justify-center font-bold text-xs">{child.name.charAt(0)}</div>
                           <div>
                              <div className="font-bold text-slate-800 text-sm">{child.name}</div>
                              <div className="text-[10px] text-slate-500">{child.age}</div>
                           </div>
                        </div>
                     ))}
                     <button className="px-4 py-2 border-2 border-dashed border-slate-200 text-slate-500 rounded-xl text-sm font-bold hover:bg-slate-50 transition">+ Dodaj</button>
                  </div>
               </div>
            </div>
         </div>

         {/* Historia Rezerwacji */}
         <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 className="font-bold text-lg text-slate-800 mb-4 flex items-center gap-2"><Calendar size={18}/> Historia uczestnictwa</h3>
            <div className="space-y-3">
               <div className="border border-slate-100 rounded-xl p-4 flex justify-between items-center bg-slate-50">
                  <div>
                     <div className="flex items-center gap-2">
                        <span className="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded">Zakończone</span>
                        <span className="font-bold text-slate-800 text-sm">Sensoplastyka dla maluchów</span>
                     </div>
                     <div className="text-xs text-slate-500 mt-1">10 Maj 2026 • Dziecko: Antosia</div>
                  </div>
                  <div className="text-right">
                     <div className="text-sm font-bold text-slate-800">55.00 zł</div>
                     <div className="text-[10px] text-slate-400">Opłacono Blikiem</div>
                  </div>
               </div>
               <div className="border border-slate-100 rounded-xl p-4 flex justify-between items-center">
                  <div>
                     <div className="flex items-center gap-2">
                        <span className="px-2 py-0.5 bg-purple-100 text-purple-700 text-[10px] font-bold rounded">Przeniesione (Nadchodzące)</span>
                        <span className="font-bold text-slate-800 text-sm">Błotna Kuchnia</span>
                     </div>
                     <div className="text-xs text-slate-500 mt-1">22 Cze 2026 • Dziecko: Antosia</div>
                  </div>
                  <div className="text-right">
                     <div className="text-sm font-bold text-slate-800">Karnet (2/4)</div>
                     <div className="text-[10px] text-slate-400 cursor-pointer underline">Historia zmian</div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   );
}

function AdminSettings() {
   const [activeTab, setActiveTab] = useState('general');

   return (
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col min-h-[600px] overflow-hidden">
         {/* Settings Sidebar / Tabs (Mobile responsive horizontally, desktop vertically) */}
         <div className="flex flex-col md:flex-row h-full">
            <div className="w-full md:w-64 bg-slate-50 border-r border-slate-200 flex flex-col p-4 gap-2 shrink-0">
               <button onClick={() => setActiveTab('general')} className={`text-left px-4 py-3 rounded-xl text-sm font-bold transition ${activeTab === 'general' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'}`}>Ustawienia Główne</button>
               <button onClick={() => setActiveTab('team')} className={`text-left px-4 py-3 rounded-xl text-sm font-bold transition ${activeTab === 'team' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'}`}>Prowadzący (Zespół)</button>
               <button onClick={() => setActiveTab('billing')} className={`text-left px-4 py-3 rounded-xl text-sm font-bold transition ${activeTab === 'billing' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'}`}>Powiadomienia Finansowe</button>
               <button onClick={() => setActiveTab('seo')} className={`text-left px-4 py-3 rounded-xl text-sm font-bold transition ${activeTab === 'seo' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'}`}><FileCode size={14} className="inline mr-2"/>SEO i robots.txt</button>
            </div>

            <div className="flex-1 p-8 overflow-y-auto">
               {activeTab === 'team' && (
                  <div className="space-y-6 animate-in fade-in">
                     <div>
                        <h2 className="text-xl font-black text-slate-800">Zarządzanie Zespołem</h2>
                        <p className="text-sm text-slate-500 mt-1">Dodaj prowadzących, aby przypisać ich do zajęć. Będą otrzymywać powiadomienia email o zapisach.</p>
                     </div>
                     
                     <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="border border-slate-200 rounded-xl p-4 flex justify-between items-center">
                           <div>
                              <div className="font-bold text-slate-800">Marta W.</div>
                              <div className="text-xs text-slate-500">marta@warsztatownia.pl</div>
                           </div>
                           <span className="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-1 rounded">Aktywna</span>
                        </div>
                        <div className="border border-slate-200 rounded-xl p-4 flex justify-between items-center">
                           <div>
                              <div className="font-bold text-slate-800">Anna K.</div>
                              <div className="text-xs text-slate-500">anna@warsztatownia.pl</div>
                           </div>
                           <span className="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-1 rounded">Aktywna</span>
                        </div>
                        <button className="border-2 border-dashed border-slate-300 rounded-xl p-4 flex items-center justify-center text-slate-500 font-bold hover:bg-slate-50 transition">
                           + Dodaj Prowadzącego
                        </button>
                     </div>
                  </div>
               )}

               {activeTab === 'billing' && (
                  <div className="space-y-6 animate-in fade-in">
                     <div>
                        <h2 className="text-xl font-black text-slate-800">Odbiorcy raportów finansowych</h2>
                        <p className="text-sm text-slate-500 mt-1">Kto powinien otrzymywać automatyczne zestawienia prowizji systemowych oraz raporty nierozpoznanych przelewów?</p>
                     </div>
                     <div className="bg-slate-50 p-5 rounded-2xl border border-slate-200 max-w-lg space-y-4">
                        <div className="flex items-center gap-3 bg-white p-3 rounded-lg border border-slate-200">
                           <Mail className="text-slate-400"/>
                           <input type="text" defaultValue="ksiegowosc@warsztatownia.pl" className="flex-1 bg-transparent text-sm font-bold text-slate-800 outline-none" />
                           <button className="text-red-500 hover:bg-red-50 p-1 rounded"><Trash2 size={16}/></button>
                        </div>
                        <div className="flex items-center gap-3 bg-white p-3 rounded-lg border border-slate-200">
                           <Mail className="text-slate-400"/>
                           <input type="text" defaultValue="szef@warsztatownia.pl" className="flex-1 bg-transparent text-sm font-bold text-slate-800 outline-none" />
                           <button className="text-red-500 hover:bg-red-50 p-1 rounded"><Trash2 size={16}/></button>
                        </div>
                        <button className="text-sm font-bold text-indigo-600 flex items-center gap-2 hover:underline"><Plus size={16}/> Dodaj adres email</button>
                     </div>
                     <button className="px-6 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-bold shadow-sm hover:bg-slate-900 transition">Zapisz ustawienia</button>
                  </div>
               )}

               {activeTab === 'seo' && (
                  <div className="space-y-6 animate-in fade-in">
                     <div>
                        <h2 className="text-xl font-black text-slate-800">SEO & Pliki Systemowe</h2>
                        <p className="text-sm text-slate-500 mt-1">Edycja plików indeksowania dla robotów (Googlebot itp.). Ostrożnie z modyfikacją!</p>
                     </div>
                     <div className="space-y-2">
                        <label className="text-sm font-bold text-slate-700 flex items-center gap-2"><FileCode size={16}/> robots.txt</label>
                        <textarea 
                           rows="6" 
                           className="w-full bg-slate-900 text-green-400 font-mono text-sm p-4 rounded-xl border-none outline-none focus:ring-2 focus:ring-indigo-500"
                           defaultValue={`User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nSitemap: https://warsztatownia.pl/sitemap.xml`}
                        ></textarea>
                     </div>
                     <div className="bg-amber-50 border border-amber-200 p-4 rounded-xl flex items-start gap-3">
                        <AlertTriangle className="text-amber-600 shrink-0"/>
                        <p className="text-xs text-amber-800 font-medium">Błędna konfiguracja tego pliku może spowodować usunięcie Twojej strony z wyników wyszukiwania Google. Zapisz zmiany tylko jeśli wiesz co robisz.</p>
                     </div>
                     <button className="px-6 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold shadow-sm hover:bg-indigo-700 transition">Aktualizuj robots.txt</button>
                  </div>
               )}

               {activeTab === 'general' && (
                  <div className="flex items-center justify-center h-full text-slate-400 font-medium">
                     Wybierz zakładkę z menu po lewej stronie.
                  </div>
               )}
            </div>
         </div>
      </div>
   );
}


const MOCK_TRANSFERS = [
  { id: 'TR-1029', date: '15.06.2026', title: 'Kiddo - Sensoplastyka Antosia', amount: '55.00', sender: 'Magdalena Kowalska', status: 'matched', resId: 'RES-001' },
  { id: 'TR-1031', date: '16.06.2026', title: 'Kiddo Janek Nowak', amount: '60.00', sender: 'Piotr Nowak', status: 'unmatched', resId: null },
];

function AdminTransfers() {
  return (
    <div className="space-y-6">
      <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
        <div className="flex gap-4 w-full md:w-auto">
          <div className="relative w-full md:w-64">
            <Search className="absolute left-3 top-2.5 text-slate-400" size={16} />
            <input type="text" placeholder="Szukaj po tytule..." className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <button className="bg-slate-800 hover:bg-slate-900 w-full md:w-auto text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center justify-center gap-2 transition shadow-sm">
          <FileText size={16} /> Importuj wyciąg (CSV)
        </button>
      </div>

      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm whitespace-nowrap">
            <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
              <tr>
                <th className="px-6 py-4 font-semibold">Data i ID</th>
                <th className="px-6 py-4 font-semibold">Nadawca</th>
                <th className="px-6 py-4 font-semibold">Tytuł przelewu</th>
                <th className="px-6 py-4 font-semibold text-right">Kwota</th>
                <th className="px-6 py-4 font-semibold">Status Dopasowania</th>
                <th className="px-6 py-4 font-semibold text-right">Akcje</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {MOCK_TRANSFERS.map((transfer, i) => (
                <tr key={i} className={`hover:bg-slate-50/50 transition ${transfer.status === 'unmatched' ? 'bg-orange-50/30' : ''}`}>
                  <td className="px-6 py-4">
                    <div className="font-bold text-slate-800">{transfer.date}</div>
                    <div className="text-xs text-slate-400 font-mono mt-0.5">{transfer.id}</div>
                  </td>
                  <td className="px-6 py-4 text-slate-700">{transfer.sender}</td>
                  <td className="px-6 py-4 text-slate-600">{transfer.title}</td>
                  <td className="px-6 py-4 text-right font-bold text-slate-800">{transfer.amount} zł</td>
                  <td className="px-6 py-4">
                    {transfer.status === 'matched' ? (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-green-100 text-green-700"><Check size={12} /> Auto ({transfer.resId})</span>
                    ) : (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-orange-100 text-orange-700 border border-orange-200"><ShieldAlert size={12} /> Brak</span>
                    )}
                  </td>
                  <td className="px-6 py-4 text-right">
                    {transfer.status === 'unmatched' ? (
                      <button className="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs font-bold transition">Dopasuj ręcznie (0% prowizji)</button>
                    ) : (
                      <button className="text-slate-400 hover:text-slate-800 transition"><EyeOff size={16}/></button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function WorkshopEditorModal({ onClose }) {
  const [activeTab, setActiveTab] = useState('general');

  return (
    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-white w-full max-w-4xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-200">
        
        <div className="flex justify-between items-center p-6 border-b border-slate-100">
          <div>
            <h2 className="text-xl font-black text-slate-800">Kreator Warsztatów</h2>
            <p className="text-sm text-slate-500">Dodaj szablon, harmonogram i karnety</p>
          </div>
          <button onClick={onClose} className="p-2 text-slate-400 hover:bg-slate-100 rounded-full transition"><X size={20} /></button>
        </div>

        <div className="flex px-6 border-b border-slate-200 bg-slate-50">
          <button onClick={() => setActiveTab('general')} className={`px-4 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'general' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500'}`}>Podstawowe</button>
          <button onClick={() => setActiveTab('schedule')} className={`px-4 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'schedule' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500'}`}>Harmonogram</button>
          <button onClick={() => setActiveTab('tickets')} className={`px-4 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'tickets' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500'}`}>Karnety</button>
        </div>

        <div className="p-6 overflow-y-auto custom-scrollbar flex-1">
          {activeTab === 'general' && (
            <div className="space-y-5">
              <div className="grid grid-cols-2 gap-5">
                <div className="space-y-1.5 col-span-2 md:col-span-1">
                  <label className="text-sm font-bold text-slate-700">Nazwa warsztatów *</label>
                  <input type="text" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 ring-indigo-500" />
                </div>
                <div className="space-y-1.5 col-span-2 md:col-span-1">
                  <label className="text-sm font-bold text-slate-700">Kategoria</label>
                  <select className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                    <option>Sensoryka</option>
                  </select>
                </div>
              </div>
              <div className="space-y-1.5">
                  <label className="text-sm font-bold text-slate-700">Opis (widoczny dla klientów)</label>
                  <textarea rows="4" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 ring-indigo-500"></textarea>
              </div>
            </div>
          )}

          {activeTab === 'schedule' && (
            <div className="space-y-6">
              <div className="space-y-4 border border-slate-200 p-5 rounded-xl">
                <h3 className="font-bold text-slate-800 flex items-center gap-2"><Repeat size={16}/> Konfiguracja Cyklu</h3>
                <div className="grid grid-cols-2 gap-5">
                  <div className="space-y-1.5">
                    <label className="text-sm font-bold text-slate-700">Dzień tygodnia</label>
                    <select className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm"><option>Środa</option></select>
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-sm font-bold text-slate-700">Godzina</label>
                    <input type="time" defaultValue="10:00" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm" />
                  </div>
                </div>
                <label className="flex items-center gap-2 text-sm font-bold text-slate-700 cursor-pointer">
                  <input type="checkbox" defaultChecked className="w-4 h-4 rounded text-indigo-600" /> Omijaj dni ustawowo wolne od pracy (święta)
                </label>
              </div>
            </div>
          )}

          {activeTab === 'tickets' && (
            <div className="space-y-6">
              <div className="space-y-3">
                <div className="flex items-center justify-between p-4 bg-white border border-indigo-200 rounded-xl shadow-sm relative overflow-hidden">
                  <div className="absolute left-0 top-0 bottom-0 w-1 bg-indigo-500"></div>
                  <div className="flex items-center gap-3 pl-2">
                    <div className="p-2 bg-indigo-50 rounded-lg text-indigo-600"><RefreshCw size={20}/></div>
                    <div>
                      <div className="font-bold text-indigo-900">Karnet: 4 wejścia</div>
                      <div className="text-xs text-slate-500 mt-1"><span className="bg-green-100 text-green-700 px-2 py-0.5 rounded text-[10px] font-bold">1 przełożenie terminu (odrabianie)</span></div>
                    </div>
                  </div>
                  <span className="font-black text-lg text-indigo-900">180 zł</span>
                </div>
              </div>
            </div>
          )}
        </div>

        <div className="p-5 border-t border-slate-100 bg-slate-50 flex justify-end gap-3 rounded-b-2xl">
          <button onClick={onClose} className="px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 rounded-xl transition">Anuluj</button>
          <button className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition">Zapisz Warsztaty</button>
        </div>
      </div>
    </div>
  );
}