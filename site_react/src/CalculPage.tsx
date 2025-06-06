import TablePrio from "./TablePrio.tsx";
import TableAD from "./TableAD.tsx";
import Navigation from "./Navigation.tsx";

export default function CalculPage(){
    return(
        <div className="Menu">
            <Navigation /><TablePrio /><TableAD />
        </div>
    );
}