import React, { useState } from 'react';
import { 
  Calendar, Users, LayoutDashboard, ChevronLeft, CheckCircle, 
  CreditCard, Menu, Clock, Ticket, User, MapPin, Search, 
  Settings, Bell, Edit, Trash2, Plus, Filter,
  X, RefreshCw, Ban, ShieldAlert, FileText, DollarSign, Lock, Unlock, Repeat, Check
} from 'lucide-react';

// --- MOCK DATA ---
const WORKSHOPS = [
  { id: 1, title: 'Sensoplastyka dla maluchów', age: '0-3 lat', price: 55, nextDate: '15 Cze, 10:00', spots: 4, totalSpots: 10, category: 'Sensoryka', img: 'bg-orange-100', color: 'text-orange-600', badge: 'bg-orange-200' },
  { id: 2, title: 'Błotna Kuchnia', age: '3-6 lat', price: 60, nextDate: '16 Cze, 16:30', spots: 0, totalSpots: 8, category: 'Brudna zabawa', img: 'bg-green-100', color: 'text-green-600', badge: 'bg-green-200' },
  { id: 3, title: 'Muzyczne Sensorki', age: '1-4 lat', price: 50, nextDate: '18 Cze, 11:00', spots: 8, totalSpots: 10, category: 'Muzyka', img: 'bg-purple-100', color: 'text-purple-600', badge: 'bg-purple-200' }
];

const ADMIN_STATS = [
  { label: 'Rezerwacje (Czerwiec)', value: '142', trend: '+12%', trendColor: 'text-green-600' },
  { label: 'Przychód (Czerwiec)', value: '7 850 zł', trend: '+5%', trendColor: 'text-green-600' },
  { label: 'Śr. zajętość zajęć', value: '88%', trend: '-2%', trendColor: 'text-red-500' },
  { label: 'Oczekujące płatności', value: '4', trend: 'Wymaga uwagi', trendColor: 'text-amber-600' }
];

const MOCK_CLIENTS = [
  { id: 'C-001', name: 'Magdalena Kowalska', email: 'magda@example.com', phone: '+48 123 456 789', registered: '10.01.2024', status: 'active', children: 1 },
  { id: 'C-002', name: 'Piotr Nowak', email: 'piotr.n@example.com', phone: '+48 987 654 321', registered: '15.03.2024', status: 'blocked', children: 2 },
  { id: 'C-003', name: 'Anna Wiśniewska', email: 'anna.w@example.com', phone: '+48 555 666 777', registered: '02.05.2024', status: 'active', children: 1 },
];

const MOCK_TRANSFERS = [
  { id: 'TR-1029', date: '15.06.2026', title: 'Kiddo - Sensoplastyka Antosia', amount: '55.00', sender: 'Magdalena Kowalska', status: 'matched', resId: 'RES-001' },
  { id: 'TR-1030', date: '15.06.2026', title: 'Przelew za zajęcia (Zosia)', amount: '50.00', sender: 'Anna Wiśniewska', status: 'matched', resId: 'RES-003' },
  { id: 'TR-1031', date: '16.06.2026', title: 'Kiddo Janek Nowak', amount: '60.00', sender: 'Piotr Nowak', status: 'unmatched', resId: null },
];

// --- MAIN APP COMPONENT ---
export default function WarsztatowniaApp() {
  const [viewMode, setViewMode] = useState('client'); // 'client' or 'admin'

  return (
    <div className="flex flex-col min-h-screen bg-slate-100 font-sans">
      {/* Dev Switcher Bar */}
      <div className="bg-slate-800 text-white p-3 flex flex-wrap justify-center items-center gap-4 text-sm z-50 shadow-md">
        <span className="opacity-70 font-medium hidden sm:inline">Wybierz widok makiety:</span>
        <div className="flex gap-2">
          <button 
            onClick={() => setViewMode('client')} 
            className={`px-4 py-2 rounded-lg transition-colors font-medium ${viewMode === 'client' ? 'bg-teal-500 text-white' : 'bg-slate-700 hover:bg-slate-600'}`}
          >
            📱 Widok Klienta (Mobile)
          </button>
          <button 
            onClick={() => setViewMode('admin')} 
            className={`px-4 py-2 rounded-lg transition-colors font-medium ${viewMode === 'admin' ? 'bg-indigo-500 text-white' : 'bg-slate-700 hover:bg-slate-600'}`}
          >
            💻 Panel Administratora
          </button>
        </div>
      </div>

      {/* RENDER VIEW */}
      <div className="flex-grow flex justify-center w-full">
        {viewMode === 'client' ? <ClientApp /> : <AdminApp />}
      </div>
    </div>
  );
}

// ==========================================
// CLIENT APP (MOBILE FIRST VIEW)
// ==========================================
function ClientApp() {
  const [activeTab, setActiveTab] = useState('home'); // home, tickets, account
  const [bookingWorkshop, setBookingWorkshop] = useState(null);

  const renderContent = () => {
    if (bookingWorkshop) {
      return <ClientBookingFlow workshop={bookingWorkshop} onBack={() => setBookingWorkshop(null)} onComplete={() => { setBookingWorkshop(null); setActiveTab('tickets'); }} />;
    }
    
    switch(activeTab) {
      case 'home': return <ClientHome onBook={setBookingWorkshop} />;
      case 'tickets': return <ClientTickets />;
      case 'account': return <ClientAccount />;
      default: return <ClientHome onBook={setBookingWorkshop} />;
    }
  };

  return (
    <div className="w-full max-w-md bg-white min-h-[850px] shadow-2xl sm:my-8 sm:rounded-[2.5rem] relative overflow-hidden border-8 border-slate-800/10 flex flex-col">
      {/* Header */}
      {!bookingWorkshop && (
        <div className="pt-10 pb-4 px-6 flex justify-between items-center bg-white z-10 sticky top-0 border-b border-gray-100">
          <div>
            <h1 className="text-2xl font-black text-teal-600 tracking-tight">Kiddo</h1>
            <p className="text-xs text-gray-500 font-medium -mt-1">Warsztatownia Sensoryczna</p>
          </div>
          <div className="w-10 h-10 rounded-full bg-teal-50 flex items-center justify-center text-teal-600">
            <User size={20} />
          </div>
        </div>
      )}

      {/* Main Content Area - Scrollable */}
      <div className="flex-grow overflow-y-auto pb-24 custom-scrollbar">
        {renderContent()}
      </div>

      {/* Bottom Navigation */}
      {!bookingWorkshop && (
        <div className="absolute bottom-0 w-full bg-white border-t border-gray-100 px-6 py-4 flex justify-between items-center pb-8 sm:pb-4 shadow-[0_-10px_20px_rgba(0,0,0,0.03)] z-20">
          <NavButton icon={<Calendar />} label="Zajęcia" active={activeTab === 'home'} onClick={() => setActiveTab('home')} />
          <NavButton icon={<Ticket />} label="Bilety" active={activeTab === 'tickets'} onClick={() => setActiveTab('tickets')} />
          <NavButton icon={<Settings />} label="Konto" active={activeTab === 'account'} onClick={() => setActiveTab('account')} />
        </div>
      )}
    </div>
  );
}

function NavButton({ icon, label, active, onClick }) {
  return (
    <button onClick={onClick} className={`flex flex-col items-center gap-1 transition-colors ${active ? 'text-teal-600' : 'text-gray-400 hover:text-gray-600'}`}>
      <div className={`${active ? 'bg-teal-50 p-1.5 rounded-xl' : 'p-1.5'}`}>
        {React.cloneElement(icon, { size: 22, strokeWidth: active ? 2.5 : 2 })}
      </div>
      <span className="text-[10px] font-semibold">{label}</span>
    </button>
  );
}

function ClientHome({ onBook }) {
  return (
    <div className="p-6 space-y-6">
      {/* Search & Filter Mock */}
      <div className="flex gap-2">
        <div className="relative flex-grow">
          <Search className="absolute left-3 top-3 text-gray-400" size={18} />
          <input type="text" placeholder="Szukaj zajęć..." className="w-full bg-gray-50 border-none rounded-2xl py-3 pl-10 pr-4 text-sm focus:ring-2 focus:ring-teal-100 outline-none" />
        </div>
        <button className="bg-gray-50 p-3 rounded-2xl text-gray-600 hover:bg-gray-100">
          <Filter size={18} />
        </button>
      </div>

      <div className="flex gap-2 overflow-x-auto pb-2 -mx-6 px-6 hide-scrollbar">
        <span className="px-5 py-2 bg-teal-600 text-white rounded-full text-sm font-medium shadow-md shadow-teal-600/20 whitespace-nowrap">Wszystkie</span>
        <span className="px-5 py-2 bg-white border border-gray-200 text-gray-600 rounded-full text-sm font-medium whitespace-nowrap">0-3 lat</span>
        <span className="px-5 py-2 bg-white border border-gray-200 text-gray-600 rounded-full text-sm font-medium whitespace-nowrap">3-6 lat</span>
      </div>

      <div className="space-y-4">
        <h2 className="font-bold text-lg text-gray-800">Nadchodzące wydarzenia</h2>
        {WORKSHOPS.map(w => (
          <div key={w.id} className="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 overflow-hidden transition-transform active:scale-[0.98]">
            <div className={`h-32 ${w.img} relative p-4 flex items-start justify-between`}>
              <span className={`px-3 py-1 bg-white/80 backdrop-blur-sm rounded-full text-xs font-bold ${w.color} shadow-sm`}>
                {w.age}
              </span>
              <button className="w-8 h-8 bg-white/80 backdrop-blur-sm rounded-full flex items-center justify-center text-gray-700 shadow-sm">
                ♡
              </button>
            </div>
            <div className="p-5">
              <div className="flex justify-between items-start mb-2">
                <h3 className="font-bold text-lg leading-tight text-gray-800">{w.title}</h3>
                <span className="font-bold text-teal-600 bg-teal-50 px-2 py-1 rounded-lg text-sm">{w.price} zł</span>
              </div>
              
              <div className="space-y-2 mb-5">
                <p className="text-sm text-gray-500 flex items-center gap-2 font-medium">
                  <Clock size={16} className="text-gray-400"/> {w.nextDate}
                </p>
                <p className={`text-sm flex items-center gap-2 font-medium ${w.spots === 0 ? 'text-red-500' : 'text-gray-500'}`}>
                  <Users size={16} className={w.spots === 0 ? 'text-red-400' : 'text-gray-400'}/> 
                  {w.spots === 0 ? 'Brak wolnych miejsc' : `Wolne miejsca: ${w.spots}/${w.totalSpots}`}
                </p>
              </div>

              <button 
                onClick={() => w.spots > 0 && onBook(w)}
                disabled={w.spots === 0}
                className={`w-full py-3.5 rounded-2xl font-bold text-sm transition-all shadow-sm
                  ${w.spots === 0 
                    ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                    : 'bg-gray-900 text-white hover:bg-gray-800 hover:shadow-md'}`}
              >
                {w.spots === 0 ? 'Zapisz na listę rezerwową' : 'Wybierz termin i zapisz'}
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function ClientBookingFlow({ workshop, onBack, onComplete }) {
  const [step, setStep] = useState(1); // 1: Termin, 2: Dziecko, 3: Płatność

  return (
    <div className="min-h-full flex flex-col bg-gray-50">
      {/* Header */}
      <div className="bg-white pt-10 pb-4 px-4 flex items-center gap-3 sticky top-0 z-10 shadow-sm">
        <button onClick={step > 1 ? () => setStep(step-1) : onBack} className="p-2 bg-gray-100 rounded-full text-gray-700">
          <ChevronLeft size={20} />
        </button>
        <div className="flex-grow">
          <div className="text-xs text-gray-500 font-medium">Rezerwacja ({step}/3)</div>
          <div className="font-bold text-gray-800 truncate">{workshop.title}</div>
        </div>
      </div>

      <div className="p-6 flex-grow">
        {step === 1 && (
          <div className="space-y-6 animate-in fade-in slide-in-from-right-4 duration-300">
            <h2 className="text-xl font-bold text-gray-800">Wybierz termin</h2>
            <div className="space-y-3">
              {[
                { date: '15 Cze (Środa)', time: '10:00 - 11:30', spots: 4, active: true },
                { date: '22 Cze (Środa)', time: '10:00 - 11:30', spots: 8, active: false },
                { date: '29 Cze (Środa)', time: '10:00 - 11:30', spots: 10, active: false }
              ].map((slot, i) => (
                <div key={i} onClick={() => setStep(2)} className={`p-4 rounded-2xl border-2 cursor-pointer transition-all ${slot.active ? 'border-teal-500 bg-teal-50' : 'border-gray-200 bg-white hover:border-teal-200'}`}>
                  <div className="flex justify-between items-center mb-1">
                    <span className="font-bold text-gray-800">{slot.date}</span>
                    <span className="text-sm font-semibold text-teal-600 bg-white px-2 py-0.5 rounded-md shadow-sm">{workshop.price} zł</span>
                  </div>
                  <div className="flex justify-between items-center text-sm">
                    <span className="text-gray-500 flex items-center gap-1"><Clock size={14}/> {slot.time}</span>
                    <span className="text-gray-500 font-medium">{slot.spots} wolnych</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-6 animate-in fade-in slide-in-from-right-4 duration-300">
            <h2 className="text-xl font-bold text-gray-800">Kogo zapisujesz?</h2>
            <p className="text-sm text-gray-500 mb-4">Wybierz dziecko z profilu lub dodaj nowe.</p>
            
            <div className="space-y-3">
              <div onClick={() => setStep(3)} className="p-4 bg-white border-2 border-gray-200 rounded-2xl flex items-center gap-4 cursor-pointer hover:border-teal-400 transition">
                <div className="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center text-amber-600 font-bold text-xl">
                  A
                </div>
                <div>
                  <div className="font-bold text-gray-800">Antosia</div>
                  <div className="text-xs text-gray-500">2 lata i 4 miesiące</div>
                </div>
                <div className="ml-auto text-gray-300"><ChevronLeft className="rotate-180" /></div>
              </div>
              
              <div className="p-4 bg-gray-100 border-2 border-dashed border-gray-300 rounded-2xl flex items-center justify-center gap-2 cursor-pointer text-gray-600 font-medium hover:bg-gray-200 transition">
                <Plus size={20} /> Dodaj kolejne dziecko
              </div>
            </div>
          </div>
        )}

        {step === 3 && (
          <div className="space-y-6 animate-in fade-in slide-in-from-right-4 duration-300">
            <h2 className="text-xl font-bold text-gray-800">Podsumowanie</h2>
            
            <div className="bg-white p-5 rounded-3xl shadow-sm border border-gray-100 space-y-4">
              <div className="flex justify-between items-start border-b border-gray-100 pb-4">
                <div>
                  <h3 className="font-bold text-gray-800">{workshop.title}</h3>
                  <p className="text-sm text-gray-500 mt-1">Antosia (2 lata)</p>
                </div>
                <span className="font-bold text-lg">{workshop.price} zł</span>
              </div>
              
              <div className="space-y-2 text-sm">
                <div className="flex items-center gap-3 text-gray-600"><Calendar size={16} className="text-teal-500"/> 15 Cze 2026 (Środa)</div>
                <div className="flex items-center gap-3 text-gray-600"><Clock size={16} className="text-teal-500"/> 10:00 - 11:30</div>
                <div className="flex items-center gap-3 text-gray-600"><MapPin size={16} className="text-teal-500"/> ul. Przykładowa 12, Warszawa</div>
              </div>
            </div>

            <h3 className="font-bold text-gray-800 mt-6">Szybka płatność</h3>
            <div className="grid grid-cols-2 gap-3">
              <button onClick={onComplete} className="bg-white border-2 border-gray-200 p-4 rounded-2xl flex flex-col items-center justify-center gap-2 hover:border-[#EB1D36] transition">
                <div className="font-black text-xl text-[#EB1D36] tracking-tighter">BLIK</div>
              </button>
              <button onClick={onComplete} className="bg-white border-2 border-gray-200 p-4 rounded-2xl flex flex-col items-center justify-center gap-2 hover:border-blue-500 transition">
                <CreditCard className="text-blue-500" />
                <span className="text-xs font-bold">Karta / Przelew</span>
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

function ClientTickets() {
  return (
    <div className="p-6 space-y-6 bg-gray-50 min-h-full">
      <h2 className="text-2xl font-bold text-gray-800">Moje bilety</h2>
      
      <div className="bg-white rounded-3xl p-5 shadow-sm border border-gray-100 relative overflow-hidden">
        <div className="absolute top-0 right-0 bg-teal-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl">NADCHODZĄCE</div>
        <div className="flex items-start justify-between border-b border-dashed border-gray-200 pb-4 mb-4 mt-2">
          <div>
            <div className="text-xs text-gray-500 font-medium mb-1">Dla: Antosia</div>
            <h3 className="font-bold text-lg text-gray-800 leading-tight">Sensoplastyka dla maluchów</h3>
          </div>
        </div>
        <div className="flex justify-between items-center text-sm font-medium">
          <div className="space-y-1">
            <div className="text-gray-800 flex items-center gap-2"><Calendar size={14} className="text-gray-400"/> 15 Cze 2026</div>
            <div className="text-gray-500 flex items-center gap-2"><Clock size={14} className="text-gray-400"/> 10:00 - 11:30</div>
          </div>
          <div className="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center p-2">
             {/* Mock QR Code */}
             <div className="w-full h-full bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48cGF0aCBmaWxsPSIjMDAwIiBkPSJNMTAgMTBoMjB2MjBIMTB6TTQwIDEwaDIwdjIwSDQwei0uLi4iLz48L3N2Zz4=')] bg-cover opacity-30"></div>
          </div>
        </div>
        <div className="mt-5 flex gap-2">
            <button className="flex-1 bg-gray-100 text-gray-600 py-2.5 rounded-xl text-xs font-bold hover:bg-gray-200 transition">Szczegóły</button>
            <button className="flex-1 bg-red-50 text-red-600 py-2.5 rounded-xl text-xs font-bold hover:bg-red-100 transition">Zgłoś nieobecność</button>
        </div>
      </div>
      
      <div className="opacity-60">
        <h3 className="text-sm font-bold text-gray-500 mb-3 ml-2 uppercase">Historia</h3>
        <div className="bg-white rounded-2xl p-4 border border-gray-200 flex justify-between items-center">
            <div>
                <h4 className="font-bold text-gray-700 text-sm">Błotna Kuchnia</h4>
                <p className="text-xs text-gray-500">10 Maj 2026 • Antosia</p>
            </div>
            <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-md font-semibold">Zakończone</span>
        </div>
      </div>
    </div>
  );
}

function ClientAccount() {
  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center gap-4 mb-8">
        <div className="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center text-teal-600 font-bold text-2xl">
          M
        </div>
        <div>
          <h2 className="text-xl font-bold text-gray-800">Magdalena Kowalska</h2>
          <p className="text-sm text-gray-500">magda@example.com</p>
        </div>
      </div>

      <div className="space-y-4">
        <h3 className="font-bold text-gray-800">Profile dzieci</h3>
        <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center text-amber-600 font-bold">A</div>
                <div>
                    <div className="font-bold text-gray-800 text-sm">Antosia</div>
                    <div className="text-xs text-gray-500">Urodzona: 01.02.2024</div>
                </div>
            </div>
            <button className="text-teal-600 text-sm font-semibold">Edytuj</button>
        </div>
        <button className="w-full py-3 bg-gray-50 text-gray-600 font-bold text-sm rounded-2xl border border-dashed border-gray-300 hover:bg-gray-100">
            + Dodaj dziecko
        </button>
      </div>

      <div className="space-y-2 mt-8 pt-6 border-t border-gray-100">
        <button className="w-full flex items-center justify-between p-3 text-gray-600 hover:bg-gray-50 rounded-xl transition">
            <span className="font-medium">Moje dane i zgody</span>
            <ChevronLeft className="rotate-180 text-gray-400" size={18} />
        </button>
        <button className="w-full flex items-center justify-between p-3 text-gray-600 hover:bg-gray-50 rounded-xl transition">
            <span className="font-medium">Historia płatności</span>
            <ChevronLeft className="rotate-180 text-gray-400" size={18} />
        </button>
        <button className="w-full flex items-center justify-between p-3 text-red-500 hover:bg-red-50 rounded-xl transition mt-4">
            <span className="font-medium">Wyloguj się</span>
        </button>
      </div>
    </div>
  );
}

// ==========================================
// ADMIN APP (DESKTOP DASHBOARD)
// ==========================================
function AdminApp() {
  const [activeMenu, setActiveMenu] = useState('dashboard');

  return (
    <div className="flex w-full min-h-screen bg-slate-50 text-slate-800">
      {/* Sidebar */}
      <aside className="w-64 bg-slate-900 text-slate-300 flex flex-col hidden md:flex shrink-0">
        <div className="p-6">
          <h1 className="text-2xl font-black text-white tracking-tight">Kiddo<span className="text-indigo-500">.</span></h1>
          <p className="text-xs text-slate-500 uppercase tracking-wider font-bold mt-1">Admin Panel</p>
        </div>
        
        <nav className="flex-1 px-4 space-y-1">
          <SidebarItem icon={<LayoutDashboard size={18}/>} label="Pulpit" active={activeMenu === 'dashboard'} onClick={() => setActiveMenu('dashboard')} />
          <SidebarItem icon={<Calendar size={18}/>} label="Grafik / Warsztaty" active={activeMenu === 'workshops'} onClick={() => setActiveMenu('workshops')} />
          <SidebarItem icon={<Ticket size={18}/>} label="Rezerwacje" badge="4" active={activeMenu === 'reservations'} onClick={() => setActiveMenu('reservations')} />
          <SidebarItem icon={<Users size={18}/>} label="Klienci" active={activeMenu === 'clients'} onClick={() => setActiveMenu('clients')} />
          <SidebarItem icon={<DollarSign size={18}/>} label="Przelewy" active={activeMenu === 'transfers'} onClick={() => setActiveMenu('transfers')} />
        </nav>
        
        <div className="p-4 border-t border-slate-800">
          <SidebarItem icon={<Settings size={18}/>} label="Ustawienia" active={false} onClick={() => {}} />
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col overflow-hidden h-screen">
        {/* Topbar */}
        <header className="bg-white h-16 border-b border-slate-200 flex items-center justify-between px-6 shrink-0 z-10">
          <div className="font-bold text-lg text-slate-800">
            {activeMenu === 'dashboard' && 'Przegląd'}
            {activeMenu === 'workshops' && 'Zarządzanie Warsztatami'}
            {activeMenu === 'reservations' && 'Wszystkie Rezerwacje'}
            {activeMenu === 'clients' && 'Zarządzanie Klientami'}
            {activeMenu === 'transfers' && 'Historia Przelewów'}
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
        <div className="flex-1 overflow-auto bg-slate-50 p-6 custom-scrollbar">
          <div className="max-w-6xl mx-auto space-y-6">
            {activeMenu === 'dashboard' && <AdminDashboard />}
            {activeMenu === 'workshops' && <AdminWorkshops />}
            {activeMenu === 'reservations' && <AdminReservations />}
            {activeMenu === 'clients' && <AdminClients />}
            {activeMenu === 'transfers' && <AdminTransfers />}
          </div>
        </div>
      </main>
    </div>
  );
}

function SidebarItem({ icon, label, active, onClick, badge }) {
  return (
    <button 
      onClick={onClick}
      className={`w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors text-sm font-medium
        ${active ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800 hover:text-white'}`}
    >
      <div className="flex items-center gap-3">
        {icon}
        <span>{label}</span>
      </div>
      {badge && (
        <span className={`px-2 py-0.5 rounded-full text-xs font-bold ${active ? 'bg-indigo-500 text-white' : 'bg-slate-700 text-slate-300'}`}>
          {badge}
        </span>
      )}
    </button>
  );
}

function AdminDashboard() {
  return (
    <>
      {/* Stats Row */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        {ADMIN_STATS.map((stat, i) => (
          <div key={i} className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div className="text-sm font-semibold text-slate-500 mb-1">{stat.label}</div>
            <div className="text-2xl font-black text-slate-800">{stat.value}</div>
            <div className={`text-xs font-bold mt-2 ${stat.trendColor}`}>{stat.trend} w stosunku do maja</div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Upcoming Classes */}
        <div className="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col">
          <div className="p-5 border-b border-slate-100 flex justify-between items-center">
            <h3 className="font-bold text-slate-800">Dzisiejsze zajęcia</h3>
            <button className="text-indigo-600 text-sm font-semibold hover:text-indigo-700">Zobacz grafik</button>
          </div>
          <div className="p-5 space-y-4">
            {[
              { time: '10:00', title: 'Sensoplastyka dla maluchów', spots: 10, taken: 10, instructor: 'Anna K.' },
              { time: '12:30', title: 'Brudna Zabawa', spots: 8, taken: 5, instructor: 'Marta W.' },
              { time: '16:00', title: 'Muzyczne Sensorki', spots: 12, taken: 11, instructor: 'Anna K.' }
            ].map((cls, i) => (
              <div key={i} className="flex items-center gap-4 p-3 hover:bg-slate-50 rounded-xl transition">
                <div className="w-16 text-center font-bold text-slate-700 bg-slate-100 py-1 rounded-lg">{cls.time}</div>
                <div className="flex-1">
                  <div className="font-bold text-slate-800 text-sm">{cls.title}</div>
                  <div className="text-xs text-slate-500">Prowadzi: {cls.instructor}</div>
                </div>
                <div className="text-right w-32">
                  <div className="text-xs font-bold mb-1 text-slate-600">Zajętość: {cls.taken}/{cls.spots}</div>
                  <div className="w-full bg-slate-200 rounded-full h-2">
                    <div 
                      className={`h-2 rounded-full ${cls.taken === cls.spots ? 'bg-green-500' : 'bg-indigo-500'}`} 
                      style={{width: `${(cls.taken/cls.spots)*100}%`}}>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Quick Actions / Alerts */}
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col">
          <div className="p-5 border-b border-slate-100">
            <h3 className="font-bold text-slate-800">Wymaga uwagi</h3>
          </div>
          <div className="p-5 space-y-3">
            <div className="bg-amber-50 border border-amber-100 p-3 rounded-xl flex items-start gap-3">
              <div className="bg-amber-100 text-amber-600 p-1.5 rounded-lg mt-0.5"><CreditCard size={16}/></div>
              <div>
                <div className="font-bold text-sm text-slate-800">4 nieopłacone rezerwacje</div>
                <div className="text-xs text-slate-600 mt-0.5">Termin płatności minął.</div>
                <button className="text-xs font-bold text-indigo-600 mt-2">Wyślij przypomnienia</button>
              </div>
            </div>
            <div className="bg-blue-50 border border-blue-100 p-3 rounded-xl flex items-start gap-3">
              <div className="bg-blue-100 text-blue-600 p-1.5 rounded-lg mt-0.5"><Users size={16}/></div>
              <div>
                <div className="font-bold text-sm text-slate-800">2 osoby na liście rezerwowej</div>
                <div className="text-xs text-slate-600 mt-0.5">Dla zajęć: Błotna Kuchnia.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

function AdminWorkshops() {
  const [isModalOpen, setIsModalOpen] = useState(false);

  return (
    <>
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col">
        <div className="p-5 border-b border-slate-100 flex justify-between items-center bg-white rounded-t-2xl sticky top-0">
          <div className="flex gap-4">
            <div className="relative">
              <Search className="absolute left-3 top-2.5 text-slate-400" size={16} />
              <input type="text" placeholder="Szukaj..." className="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
          <button onClick={() => setIsModalOpen(true)} className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition shadow-sm">
            <Plus size={16} /> Dodaj zajęcia
          </button>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm whitespace-nowrap">
            <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
              <tr>
                <th className="px-6 py-4 font-semibold">Nazwa warsztatu</th>
                <th className="px-6 py-4 font-semibold">Wiek</th>
                <th className="px-6 py-4 font-semibold">Najbliższy termin</th>
                <th className="px-6 py-4 font-semibold">Zajętość</th>
                <th className="px-6 py-4 font-semibold text-right">Cena</th>
                <th className="px-6 py-4 font-semibold text-center">Akcje</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {WORKSHOPS.map(w => (
                <tr key={w.id} className="hover:bg-slate-50/50 transition">
                  <td className="px-6 py-4">
                    <div className="font-bold text-slate-800">{w.title}</div>
                    <div className="text-xs text-slate-500">{w.category}</div>
                  </td>
                  <td className="px-6 py-4"><span className={`px-2 py-1 rounded-md text-xs font-bold ${w.badge} ${w.color}`}>{w.age}</span></td>
                  <td className="px-6 py-4 text-slate-600 font-medium">{w.nextDate}</td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-2">
                      <div className="w-16 bg-slate-200 rounded-full h-1.5">
                        <div className={`h-1.5 rounded-full ${(w.totalSpots - w.spots) === w.totalSpots ? 'bg-red-500' : 'bg-green-500'}`} style={{width: `${((w.totalSpots - w.spots)/w.totalSpots)*100}%`}}></div>
                      </div>
                      <span className="text-xs font-bold text-slate-600">{w.totalSpots - w.spots}/{w.totalSpots}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4 text-right font-bold text-slate-800">{w.price} zł</td>
                  <td className="px-6 py-4 flex justify-center gap-2">
                    <button onClick={() => setIsModalOpen(true)} className="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"><Edit size={16}/></button>
                    <button className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"><Trash2 size={16}/></button>
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

function AdminReservations() {
  const mockReservations = [
    { id: 'RES-001', client: 'Magdalena Kowalska', child: 'Antosia (2l)', workshop: 'Sensoplastyka dla maluchów', date: '15.06.2026', status: 'Opłacone', statusColor: 'bg-green-100 text-green-700' },
    { id: 'RES-002', client: 'Piotr Nowak', child: 'Janek (4l)', workshop: 'Błotna Kuchnia', date: '16.06.2026', status: 'Oczekuje', statusColor: 'bg-amber-100 text-amber-700' },
    { id: 'RES-003', client: 'Anna Wiśniewska', child: 'Zosia (3l)', workshop: 'Muzyczne Sensorki', date: '18.06.2026', status: 'Opłacone', statusColor: 'bg-green-100 text-green-700' },
    { id: 'RES-004', client: 'Kamil Zieliński', child: 'Krzyś (5l)', workshop: 'Błotna Kuchnia', date: '16.06.2026', status: 'Anulowane', statusColor: 'bg-red-100 text-red-700' },
  ];

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm">
      <div className="p-5 border-b border-slate-100 flex justify-between items-center">
        <h3 className="font-bold text-slate-800">Ostatnie rezerwacje</h3>
        <button className="text-sm font-semibold text-slate-600 border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-50">Filtruj</button>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm whitespace-nowrap">
          <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
            <tr>
              <th className="px-6 py-4 font-semibold">ID</th>
              <th className="px-6 py-4 font-semibold">Klient / Dziecko</th>
              <th className="px-6 py-4 font-semibold">Zajęcia</th>
              <th className="px-6 py-4 font-semibold">Status</th>
              <th className="px-6 py-4 font-semibold text-right">Akcje</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {mockReservations.map((res, i) => (
              <tr key={i} className="hover:bg-slate-50/50 transition">
                <td className="px-6 py-4 text-xs font-mono text-slate-500">{res.id}</td>
                <td className="px-6 py-4">
                  <div className="font-bold text-slate-800">{res.client}</div>
                  <div className="text-xs text-slate-500">{res.child}</div>
                </td>
                <td className="px-6 py-4">
                  <div className="font-medium text-slate-800">{res.workshop}</div>
                  <div className="text-xs text-slate-500">{res.date}</div>
                </td>
                <td className="px-6 py-4">
                  <span className={`px-2.5 py-1 rounded-md text-xs font-bold ${res.statusColor}`}>
                    {res.status}
                  </span>
                </td>
                <td className="px-6 py-4 text-right">
                  <button className="text-indigo-600 font-semibold text-xs hover:text-indigo-800">Zarządzaj</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function AdminClients() {
  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col">
      <div className="p-5 border-b border-slate-100 flex justify-between items-center bg-white rounded-t-2xl sticky top-0">
        <div className="relative w-64">
          <Search className="absolute left-3 top-2.5 text-slate-400" size={16} />
          <input type="text" placeholder="Szukaj klienta..." className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </div>
      
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm whitespace-nowrap">
          <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
            <tr>
              <th className="px-6 py-4 font-semibold">Dane Klienta</th>
              <th className="px-6 py-4 font-semibold">Kontakt</th>
              <th className="px-6 py-4 font-semibold">Dzieci</th>
              <th className="px-6 py-4 font-semibold">Zarejestrowano</th>
              <th className="px-6 py-4 font-semibold">Status</th>
              <th className="px-6 py-4 font-semibold text-right">Akcje</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {MOCK_CLIENTS.map(client => (
              <tr key={client.id} className={`hover:bg-slate-50/50 transition ${client.status === 'blocked' ? 'opacity-70 bg-slate-50' : ''}`}>
                <td className="px-6 py-4">
                  <div className="font-bold text-slate-800 flex items-center gap-2">
                    {client.status === 'blocked' && <Lock size={14} className="text-red-500" />}
                    {client.name}
                  </div>
                  <div className="text-xs text-slate-500 font-mono mt-0.5">{client.id}</div>
                </td>
                <td className="px-6 py-4">
                  <div className="text-slate-800">{client.email}</div>
                  <div className="text-xs text-slate-500">{client.phone}</div>
                </td>
                <td className="px-6 py-4 text-slate-600 font-medium">{client.children} profili</td>
                <td className="px-6 py-4 text-slate-600">{client.registered}</td>
                <td className="px-6 py-4">
                  <span className={`px-2.5 py-1 rounded-md text-xs font-bold ${client.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                    {client.status === 'active' ? 'Aktywny' : 'Zablokowany'}
                  </span>
                </td>
                <td className="px-6 py-4 flex justify-end gap-2">
                  <button className="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Edytuj">
                    <Edit size={16}/>
                  </button>
                  {client.status === 'active' ? (
                    <button className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Zablokuj Użytkownika">
                      <Ban size={16}/>
                    </button>
                  ) : (
                    <button className="p-1.5 text-red-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition" title="Odblokuj">
                      <Unlock size={16}/>
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function AdminTransfers() {
  return (
    <div className="space-y-6">
      {/* Filters/Import Topbar */}
      <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
        <div className="flex gap-4 w-full md:w-auto">
          <div className="relative w-full md:w-64">
            <Search className="absolute left-3 top-2.5 text-slate-400" size={16} />
            <input type="text" placeholder="Szukaj po tytule / nadawcy..." className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <select className="py-2 px-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none text-slate-600">
            <option>Wszystkie statusy</option>
            <option>Nieprzypisane</option>
            <option>Przypisane</option>
          </select>
        </div>
        <button className="bg-slate-800 hover:bg-slate-900 w-full md:w-auto text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center justify-center gap-2 transition shadow-sm">
          <FileText size={16} /> Importuj wyciąg (CSV/MT940)
        </button>
      </div>

      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm whitespace-nowrap">
            <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
              <tr>
                <th className="px-6 py-4 font-semibold">Data i ID Wpływu</th>
                <th className="px-6 py-4 font-semibold">Nadawca</th>
                <th className="px-6 py-4 font-semibold">Tytuł przelewu</th>
                <th className="px-6 py-4 font-semibold text-right">Kwota</th>
                <th className="px-6 py-4 font-semibold">Dopasowanie (Rezerwacja)</th>
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
                  <td className="px-6 py-4 max-w-xs truncate text-slate-600" title={transfer.title}>{transfer.title}</td>
                  <td className="px-6 py-4 text-right font-bold text-slate-800">{transfer.amount} zł</td>
                  <td className="px-6 py-4">
                    {transfer.status === 'matched' ? (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-green-100 text-green-700">
                        <Check size={12} /> {transfer.resId}
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-orange-100 text-orange-700 border border-orange-200">
                        <ShieldAlert size={12} /> Brak dopasowania
                      </span>
                    )}
                  </td>
                  <td className="px-6 py-4 text-right flex justify-end gap-2">
                    {transfer.status === 'unmatched' ? (
                      <button className="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs font-bold transition">Dopasuj ręcznie</button>
                    ) : (
                      <button className="px-3 py-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg text-xs font-semibold transition">Odłącz</button>
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
        
        {/* Header */}
        <div className="flex justify-between items-center p-6 border-b border-slate-100">
          <div>
            <h2 className="text-xl font-black text-slate-800">Kreator Warsztatów</h2>
            <p className="text-sm text-slate-500">Dodaj lub edytuj wydarzenie i zasady sprzedaży</p>
          </div>
          <button onClick={onClose} className="p-2 text-slate-400 hover:bg-slate-100 rounded-full transition">
            <X size={20} />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex px-6 border-b border-slate-200 bg-slate-50">
          <button onClick={() => setActiveTab('general')} className={`px-4 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'general' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>Podstawowe</button>
          <button onClick={() => setActiveTab('schedule')} className={`px-4 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'schedule' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>Harmonogram i Cykliczność</button>
          <button onClick={() => setActiveTab('tickets')} className={`px-4 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'tickets' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>Bilety, Karnety i Zasady</button>
        </div>

        {/* Form Body - Scrollable */}
        <div className="p-6 overflow-y-auto custom-scrollbar flex-1">
          
          {/* TAB: GENERAL */}
          {activeTab === 'general' && (
            <div className="space-y-5">
              <div className="grid grid-cols-2 gap-5">
                <div className="space-y-1.5 col-span-2 md:col-span-1">
                  <label className="text-sm font-bold text-slate-700">Nazwa warsztatów *</label>
                  <input type="text" placeholder="np. Błotna Kuchnia" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                </div>
                <div className="space-y-1.5 col-span-2 md:col-span-1">
                  <label className="text-sm font-bold text-slate-700">Kategoria</label>
                  <select className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none">
                    <option>Sensoryka</option>
                    <option>Muzyka</option>
                    <option>Ruchowe</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-5">
                <div className="space-y-1.5">
                  <label className="text-sm font-bold text-slate-700">Przedział wiekowy</label>
                  <input type="text" placeholder="np. 0-3 lat" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none" />
                </div>
                <div className="space-y-1.5">
                  <label className="text-sm font-bold text-slate-700">Limit miejsc (pojemność sali)</label>
                  <input type="number" defaultValue="10" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none" />
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-sm font-bold text-slate-700">Opis (widoczny dla klientów)</label>
                <textarea rows="4" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none" placeholder="Opisz co będzie się działo..."></textarea>
              </div>
            </div>
          )}

          {/* TAB: SCHEDULE */}
          {activeTab === 'schedule' && (
            <div className="space-y-6">
              <div className="p-4 rounded-xl border-2 border-indigo-100 bg-indigo-50/50 flex gap-4">
                <div className="flex-1">
                  <label className="flex items-center gap-2 text-sm font-bold text-indigo-900 mb-1">
                    <input type="radio" name="scheduletype" className="w-4 h-4 text-indigo-600" /> Wydarzenie jednorazowe
                  </label>
                  <p className="text-xs text-indigo-700 ml-6">Klasyczne zajęcia w konkretnym dniu.</p>
                </div>
                <div className="flex-1">
                  <label className="flex items-center gap-2 text-sm font-bold text-indigo-900 mb-1">
                    <input type="radio" name="scheduletype" defaultChecked className="w-4 h-4 text-indigo-600" /> Cykliczne (Powtarzalne)
                  </label>
                  <p className="text-xs text-indigo-700 ml-6">Tworzy serię wydarzeń np. co tydzień.</p>
                </div>
              </div>

              <div className="space-y-4 border border-slate-200 p-5 rounded-xl">
                <h3 className="font-bold text-slate-800 flex items-center gap-2"><Repeat size={16}/> Konfiguracja Cyklu</h3>
                
                <div className="grid grid-cols-2 gap-5">
                  <div className="space-y-1.5">
                    <label className="text-sm font-bold text-slate-700">Dzień tygodnia</label>
                    <select className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none">
                      <option>Poniedziałek</option>
                      <option>Wtorek</option>
                      <option selected>Środa</option>
                      <option>Czwartek</option>
                      <option>Piątek</option>
                      <option>Sobota</option>
                      <option>Niedziela</option>
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-sm font-bold text-slate-700">Godzina (Od - Do)</label>
                    <div className="flex items-center gap-2">
                      <input type="time" defaultValue="10:00" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none" />
                      <span className="text-slate-400">-</span>
                      <input type="time" defaultValue="11:30" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none" />
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-5 pt-2">
                  <div className="space-y-1.5">
                    <label className="text-sm font-bold text-slate-700">Początek cyklu</label>
                    <input type="date" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none" />
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-sm font-bold text-slate-700">Zakończenie cyklu (opcjonalne)</label>
                    <input type="date" className="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none" />
                  </div>
                </div>

                <div className="pt-2">
                  <label className="flex items-center gap-2 text-sm font-bold text-slate-700 cursor-pointer">
                    <input type="checkbox" defaultChecked className="w-4 h-4 rounded text-indigo-600" />
                    Omijaj dni ustawowo wolne od pracy (święta)
                  </label>
                </div>
              </div>
            </div>
          )}

          {/* TAB: TICKETS & PASSES */}
          {activeTab === 'tickets' && (
            <div className="space-y-6">
              
              {/* Existing defined tickets */}
              <div className="space-y-3">
                <h3 className="font-bold text-slate-800">Skonfigurowane warianty biletowe</h3>
                
                {/* Single Ticket variant */}
                <div className="flex items-center justify-between p-4 bg-white border border-slate-200 rounded-xl shadow-sm">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-slate-100 rounded-lg text-slate-600"><Ticket size={20}/></div>
                    <div>
                      <div className="font-bold text-slate-800">Wejście jednorazowe</div>
                      <div className="text-xs text-slate-500">Standardowy bilet na 1 zajęcia</div>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <span className="font-black text-lg text-slate-800">55 zł</span>
                    <button className="text-slate-400 hover:text-slate-700"><Edit size={16}/></button>
                  </div>
                </div>

                {/* Complex Pass variant */}
                <div className="flex items-center justify-between p-4 bg-white border border-indigo-200 rounded-xl shadow-sm relative overflow-hidden">
                  <div className="absolute left-0 top-0 bottom-0 w-1 bg-indigo-500"></div>
                  <div className="flex items-center gap-3 pl-2">
                    <div className="p-2 bg-indigo-50 rounded-lg text-indigo-600"><RefreshCw size={20}/></div>
                    <div>
                      <div className="font-bold text-indigo-900">Karnet: 4 wejścia</div>
                      <div className="text-xs text-slate-500 mt-1 flex gap-2">
                        <span className="bg-slate-100 px-2 py-0.5 rounded text-[10px] font-bold">Ważny 30 dni</span>
                        <span className="bg-green-100 text-green-700 px-2 py-0.5 rounded text-[10px] font-bold">1 przełożenie terminu</span>
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <span className="font-black text-lg text-indigo-900">180 zł</span>
                    <button className="text-slate-400 hover:text-slate-700"><Edit size={16}/></button>
                  </div>
                </div>
              </div>

              {/* Form to add new variant */}
              <div className="mt-8 border-t border-slate-200 pt-6">
                <h3 className="font-bold text-slate-800 mb-4">+ Dodaj nowy wariant</h3>
                <div className="bg-slate-50 p-5 rounded-xl border border-slate-200 space-y-4">
                  
                  <div className="grid grid-cols-3 gap-4">
                    <div className="space-y-1.5 col-span-2">
                      <label className="text-sm font-bold text-slate-700">Nazwa wariantu</label>
                      <input type="text" placeholder="np. Karnet Miesięczny" className="w-full p-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none" />
                    </div>
                    <div className="space-y-1.5">
                      <label className="text-sm font-bold text-slate-700">Cena (zł)</label>
                      <input type="number" placeholder="0.00" className="w-full p-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none" />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <label className="text-sm font-bold text-slate-700">Typ</label>
                      <select className="w-full p-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none">
                        <option>Pojedyncze wejście</option>
                        <option selected>Karnet (Wielokrotne wejście)</option>
                      </select>
                    </div>
                    <div className="space-y-1.5">
                      <label className="text-sm font-bold text-slate-700">Liczba wejść</label>
                      <input type="number" defaultValue="4" className="w-full p-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none" />
                    </div>
                  </div>

                  {/* Advanced Rules for Passes */}
                  <div className="bg-white p-4 rounded-lg border border-slate-200 space-y-3 mt-2">
                    <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider">Zasady Karnetu (Zgodnie z regulaminem)</h4>
                    
                    <div className="flex items-center justify-between">
                      <label className="text-sm font-medium text-slate-700">Okres ważności od zakupu/pierwszych zajęć</label>
                      <select className="p-2 border border-slate-200 rounded-md text-sm">
                        <option>4 tygodnie (30 dni)</option>
                        <option>2 miesiące</option>
                        <option>Brak limitu</option>
                      </select>
                    </div>
                    
                    <div className="flex items-center justify-between pt-2 border-t border-slate-100">
                      <label className="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                        <input type="checkbox" defaultChecked className="w-4 h-4 rounded text-indigo-600" />
                        Zezwól klientowi na samodzielne przełożenie wejścia
                      </label>
                      <select className="p-2 border border-slate-200 rounded-md text-sm w-32">
                        <option>Tylko 1 raz</option>
                        <option>Do 2 razy</option>
                        <option>Bez limitu</option>
                      </select>
                    </div>
                    <p className="text-[10px] text-slate-400 mt-1">
                      * Opcja "Zgłoś nieobecność i odbierz później" pojawi się w aplikacji klienta dla tego karnetu.
                    </p>
                  </div>

                  <div className="flex justify-end pt-2">
                    <button className="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-bold">Zapisz Wariant</button>
                  </div>
                </div>
              </div>

            </div>
          )}

        </div>

        {/* Footer Actions */}
        <div className="p-5 border-t border-slate-100 bg-slate-50 flex justify-end gap-3 rounded-b-2xl">
          <button onClick={onClose} className="px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 rounded-xl transition">Anuluj</button>
          <button className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition">Zapisz Warsztaty</button>
        </div>
      </div>
    </div>
  );
}