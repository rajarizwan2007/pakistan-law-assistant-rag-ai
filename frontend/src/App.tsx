import { SearchPanel } from './components/SearchPanel'
import { SourceList } from './components/SourceList'
import { SystemStatus } from './components/SystemStatus'

function App() {
  return (
    <main className="container">
      <header>
        <h1>Pakistan Law Assistant</h1>
        <p className="disclaimer">Informational only — not legal advice.</p>
      </header>

      <SearchPanel />
      <SourceList />
      <SystemStatus />
    </main>
  )
}

export default App
